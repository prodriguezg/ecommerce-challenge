<?php

namespace App\Services;

use App\Enums\ImportMode;
use App\Enums\InventoryAdjustmentReason;
use App\Enums\UnknownCategoryPolicy;
use App\Exceptions\ProductCsvFileException;
use App\Http\Requests\Api\V1\Admin\ProductImportRequest;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Import;
use App\Models\Inventory;
use App\Models\InventoryAdjustment;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ProductCsvImporter
{
    /** @var list<string> */
    private const SUPPORTED_HEADERS = ['name', 'sku', 'description', 'category', 'price', 'stock', 'weight_kg', 'reason'];

    /** @var list<string> */
    private const REQUIRED_HEADERS = ['name', 'sku', 'price', 'stock', 'weight_kg'];

    public function import(ProductImportRequest $request): Import
    {
        $file = $request->file('file');
        if (! $file instanceof UploadedFile) {
            throw new ProductCsvFileException(422, 'Invalid CSV file', 'A CSV file is required.', 'csv_file_required');
        }

        if (Str::lower($file->getClientOriginalExtension()) !== 'csv') {
            throw new ProductCsvFileException(415, 'Unsupported media type', 'The uploaded file must use the .csv extension.', 'csv_unsupported_media_type');
        }

        $this->validateFileSize($file);
        [$headers, $rows] = $this->parse($file);
        $currencyId = Currency::query()->where('is_base', true)->whereNull('deleted_at')->value('id');
        if (! is_string($currencyId)) {
            throw new ProductCsvFileException(422, 'Import unavailable', 'An active base currency must exist before products can be imported.', 'csv_base_currency_missing');
        }

        $mode = ImportMode::from($request->string('mode')->toString());
        $categoryPolicy = UnknownCategoryPolicy::from($request->string('unknown_category_policy')->toString());
        $overrideStock = $request->boolean('override_stock');
        $actor = $request->user();
        if (! $actor instanceof User) {
            throw new RuntimeException('Authenticated import actor is unavailable.');
        }

        $import = Import::create([
            'actor_user_id' => $actor->getKey(),
            'original_filename' => Str::limit(basename($file->getClientOriginalName()), 255, ''),
            'mode' => $mode,
            'unknown_category_policy' => $categoryPolicy,
            'stock_override' => $overrideStock,
            'stock_override_confirmed_at' => $overrideStock ? now() : null,
            'total_count' => count($rows),
        ]);

        $duplicateSkus = $this->duplicateSkus($rows);
        $rejections = [];
        $importedCount = 0;
        $warningCount = 0;

        foreach ($rows as $row) {
            $errors = $this->rowErrors($row['values']);
            $normalizedSku = Str::upper(trim($row['values']['sku'] ?? ''));
            if ($normalizedSku !== '' && isset($duplicateSkus[$normalizedSku])) {
                $errors[] = 'SKU occurs more than once in this file; every occurrence was rejected.';
            }

            if ($errors === []) {
                try {
                    $warning = $this->processRow($row['values'], $mode, $categoryPolicy, $overrideStock, $currencyId, $actor, $import);
                    $importedCount++;
                    $warningCount += (int) $warning;

                    continue;
                } catch (ProductCsvFileException $exception) {
                    $errors = $exception->errors['row'] ?? [$exception->detail];
                } catch (QueryException $exception) {
                    report($exception);
                    $errors = ['A catalog conflict prevented this row from being imported.'];
                } catch (Throwable $exception) {
                    report($exception);
                    $errors = ['An internal error prevented this row from being imported.'];
                }
            }

            $rejections[] = ['cells' => $row['cells'], 'reason' => implode('; ', array_values(array_unique($errors)))];
        }

        $import->update([
            'imported_count' => $importedCount,
            'warning_count' => $warningCount,
            'rejected_count' => count($rejections),
            'rejection_report' => $this->rejectionReport($headers, $rejections),
        ]);

        return $import->fresh();
    }

    private function validateFileSize(UploadedFile $file): void
    {
        $maxBytes = max(1, (int) config('api.csv_import.max_bytes', 5 * 1024 * 1024));
        $size = $file->getSize();
        if ($size === false || $size > $maxBytes) {
            throw new ProductCsvFileException(413, 'CSV file too large', 'The CSV file exceeds the configured byte limit.', 'csv_file_too_large');
        }
    }

    /** @return array{0: list<string>, 1: list<array{cells: list<string>, values: array<string, string>}>} */
    private function parse(UploadedFile $file): array
    {
        $contents = file_get_contents($file->getRealPath());
        if (! is_string($contents) || ! mb_check_encoding($contents, 'UTF-8')) {
            throw new ProductCsvFileException(422, 'Invalid CSV encoding', 'The CSV file must be valid UTF-8.', 'csv_invalid_encoding');
        }

        $stream = fopen('php://temp', 'r+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to allocate the CSV parser stream.');
        }
        fwrite($stream, $contents);
        rewind($stream);

        $rawHeaders = fgetcsv($stream, escape: '');
        if (! is_array($rawHeaders)) {
            fclose($stream);
            throw new ProductCsvFileException(422, 'Invalid CSV header', 'The CSV file must contain a header row.', 'csv_header_missing');
        }
        $rawHeaders[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) ($rawHeaders[0] ?? '')) ?? '';
        $headers = array_map(fn ($header): string => Str::lower(trim((string) $header)), $rawHeaders);
        $this->validateHeaders($headers);

        $rows = [];
        $maxRows = max(1, (int) config('api.csv_import.max_rows', 10_000));
        while (($cells = fgetcsv($stream, escape: '')) !== false) {
            if (count($rows) >= $maxRows) {
                fclose($stream);
                throw new ProductCsvFileException(422, 'CSV row limit exceeded', 'The CSV file exceeds the configured data-row limit.', 'csv_row_limit_exceeded');
            }
            $stringCells = array_map(fn ($cell): string => (string) $cell, $cells);
            $values = [];
            foreach ($headers as $index => $header) {
                $values[$header] = $stringCells[$index] ?? '';
            }
            if (count($stringCells) !== count($headers)) {
                $values['__column_error'] = 'Each data row must contain exactly '.count($headers).' columns.';
            }
            $rows[] = ['cells' => $stringCells, 'values' => $values];
        }
        fclose($stream);

        return [$headers, $rows];
    }

    /** @param list<string> $headers */
    private function validateHeaders(array $headers): void
    {
        $errors = [];
        if (count($headers) !== count(array_unique($headers))) {
            $errors[] = 'Header names must not be duplicated.';
        }
        $unsupported = array_values(array_diff($headers, self::SUPPORTED_HEADERS));
        if ($unsupported !== []) {
            $errors[] = 'Unsupported columns: '.implode(', ', $unsupported).'.';
        }
        $missing = array_values(array_diff(self::REQUIRED_HEADERS, $headers));
        if ($missing !== []) {
            $errors[] = 'Missing required columns: '.implode(', ', $missing).'.';
        }
        if ($errors !== []) {
            throw new ProductCsvFileException(422, 'Invalid CSV header', 'The CSV header does not match the product import contract.', 'csv_invalid_header', ['file' => $errors]);
        }
    }

    /**
     * @param  list<array{cells: list<string>, values: array<string, string>}>  $rows
     * @return array<string, bool>
     */
    private function duplicateSkus(array $rows): array
    {
        $counts = [];
        foreach ($rows as $row) {
            $sku = Str::upper(trim($row['values']['sku'] ?? ''));
            if ($sku !== '') {
                $counts[$sku] = ($counts[$sku] ?? 0) + 1;
            }
        }

        return collect($counts)->filter(fn (int $count): bool => $count > 1)->map(fn (): bool => true)->all();
    }

    /**
     * @param  array<string, string>  $row
     * @return list<string>
     */
    private function rowErrors(array $row): array
    {
        $validator = Validator::make($row, [
            'name' => ['required', 'string', 'max:200', 'not_regex:/^\s*$/'],
            'sku' => ['required', 'string', 'max:100', 'not_regex:/^\s*$/'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category' => ['nullable', 'string', 'max:200'],
            'price' => ['required', 'decimal:2', 'min:0', 'max:999999999999999.9999'],
            'stock' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'weight_kg' => ['required', 'decimal:0,4', 'min:0', 'max:99999999.9999'],
        ]);
        $errors = $validator->errors()->all();
        if (isset($row['__column_error'])) {
            $errors[] = $row['__column_error'];
        }

        return $errors;
    }

    /** @param array<string, string> $row */
    private function processRow(array $row, ImportMode $mode, UnknownCategoryPolicy $categoryPolicy, bool $overrideStock, string $currencyId, User $actor, Import $import): bool
    {
        return DB::transaction(function () use ($row, $mode, $categoryPolicy, $overrideStock, $currencyId, $actor, $import): bool {
            $normalizedSku = Str::upper(trim($row['sku']));
            $product = Product::withTrashed()->where('normalized_sku', $normalizedSku)->lockForUpdate()->first();
            if ($product?->trashed()) {
                $this->rejectRow('Soft-deleted SKUs cannot be imported.');
            }
            if ($product !== null && $mode === ImportMode::CreateOnly) {
                $this->rejectRow('SKU already exists and create-only mode does not update products.');
            }
            if ($product === null && $mode === ImportMode::UpdateOnly) {
                $this->rejectRow('SKU does not exist and update-only mode does not create products.');
            }
            if ($product === null && DB::table('product_sku_reservations')->where('normalized_sku', $normalizedSku)->exists()) {
                $this->rejectRow('SKU is permanently reserved and cannot be reused.');
            }

            [$categoryId, $warning] = $this->category($row['category'] ?? '', $categoryPolicy);
            $attributes = [
                'name' => trim($row['name']),
                'sku' => trim($row['sku']),
                'description' => trim($row['description'] ?? '') === '' ? null : $row['description'],
                'category_id' => $categoryId,
                'price' => $row['price'],
                'weight_kg' => $row['weight_kg'],
            ];

            if ($product === null) {
                $product = Product::create($attributes + ['currency_id' => $currencyId, 'tax_id' => null, 'version' => 1]);
                DB::table('product_sku_reservations')->insert(['normalized_sku' => $product->normalized_sku, 'product_id' => $product->getKey(), 'created_at' => now()]);
                $inventory = $product->inventory()->create(['stock_on_hand' => (int) $row['stock'], 'version' => 1]);
                if ($inventory->stock_on_hand > 0) {
                    $this->recordAdjustment($inventory, $product, 0, (int) $inventory->stock_on_hand, $actor, $import, 'Initial stock from product CSV import.');
                }
            } else {
                $product->fill($attributes + ['version' => $product->version + 1])->save();
                if ($overrideStock) {
                    $inventory = Inventory::where('product_id', $product->getKey())->lockForUpdate()->firstOrFail();
                    $newQuantity = (int) $row['stock'];
                    if ($newQuantity < $inventory->reservedQuantity()) {
                        $this->rejectRow('Stock override cannot be below the active reserved quantity.');
                    }
                    $previousQuantity = (int) $inventory->stock_on_hand;
                    $inventory->fill(['stock_on_hand' => $newQuantity, 'version' => $inventory->version + 1])->save();
                    $this->recordAdjustment($inventory, $product, $previousQuantity, $newQuantity, $actor, $import, 'Confirmed stock override from product CSV import.');
                }
            }

            return $warning;
        }, 3);
    }

    /** @return array{0: string|null, 1: bool} */
    private function category(string $name, UnknownCategoryPolicy $policy): array
    {
        $name = trim($name);
        if ($name === '') {
            return [null, false];
        }
        $normalizedName = Str::lower(Str::squish($name));
        $category = Category::query()->where('normalized_name', $normalizedName)->first();
        if ($category !== null) {
            return [$category->getKey(), false];
        }
        if ($policy === UnknownCategoryPolicy::Reject) {
            $this->rejectRow('Category does not exist.');
        }
        if ($policy === UnknownCategoryPolicy::Uncategorized) {
            return [null, true];
        }
        $category = Category::create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::lower((string) Str::ulid()), 'version' => 1]);

        return [$category->getKey(), false];
    }

    private function recordAdjustment(Inventory $inventory, Product $product, int $previousQuantity, int $newQuantity, User $actor, Import $import, string $note): void
    {
        InventoryAdjustment::unguarded(fn (): InventoryAdjustment => InventoryAdjustment::create([
            'inventory_id' => $inventory->getKey(),
            'product_id' => $product->getKey(),
            'previous_quantity' => $previousQuantity,
            'new_quantity' => $newQuantity,
            'delta' => $newQuantity - $previousQuantity,
            'reason' => InventoryAdjustmentReason::CsvImport,
            'note' => $note,
            'actor_user_id' => $actor->getKey(),
            'import_id' => $import->getKey(),
        ]));
    }

    private function rejectRow(string $message): never
    {
        throw new ProductCsvFileException(422, 'CSV row rejected', $message, 'csv_row_rejected', ['row' => [$message]]);
    }

    /**
     * @param  list<string>  $headers
     * @param  list<array{cells: list<string>, reason: string}>  $rejections
     */
    private function rejectionReport(array $headers, array $rejections): string
    {
        $reportHeaders = array_values(array_filter($headers, fn (string $header): bool => $header !== 'reason'));
        $stream = fopen('php://temp', 'r+b');
        if ($stream === false) {
            throw new RuntimeException('Unable to allocate the rejection report stream.');
        }
        fputcsv($stream, [...$reportHeaders, 'reason'], escape: '');
        $reasonIndex = array_search('reason', $headers, true);
        foreach ($rejections as $rejection) {
            $cells = $rejection['cells'];
            if ($reasonIndex !== false) {
                unset($cells[$reasonIndex]);
                $cells = array_values($cells);
            }
            $cells = array_slice(array_pad($cells, count($reportHeaders), ''), 0, count($reportHeaders));
            fputcsv($stream, [...array_map($this->neutralizeCell(...), $cells), $this->neutralizeCell($rejection['reason'])], escape: '');
        }
        rewind($stream);
        $contents = stream_get_contents($stream);
        fclose($stream);

        return is_string($contents) ? $contents : '';
    }

    private function neutralizeCell(string $cell): string
    {
        return preg_match('/^[\t\r ]*[=+\-@]/u', $cell) === 1 ? "'".$cell : $cell;
    }
}
