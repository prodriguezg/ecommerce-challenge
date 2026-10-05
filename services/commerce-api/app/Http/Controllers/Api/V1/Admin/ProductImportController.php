<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\ProductCsvFileException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Admin\ProductImportRequest;
use App\Http\Resources\Api\V1\Admin\ProductImportResource;
use App\Http\Responses\ProblemDetails;
use App\Models\Import;
use App\Services\AdminAuditLogger;
use App\Services\ProductCsvImporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ProductImportController extends Controller
{
    public function __construct(
        private readonly ProductCsvImporter $importer,
        private readonly AdminAuditLogger $audit,
    ) {}

    public function store(ProductImportRequest $request): ProductImportResource|JsonResponse
    {
        try {
            $import = $this->importer->import($request);
        } catch (ProductCsvFileException $exception) {
            return ProblemDetails::response(
                $request,
                $exception->status,
                $exception->title,
                $exception->detail,
                $exception->problemCode,
                $exception->errors,
            );
        }

        $this->audit->record($request, 'product_import.completed', $import, null, $import->toArray());

        return new ProductImportResource($import);
    }

    public function show(string $import): ProductImportResource
    {
        return new ProductImportResource(Import::findOrFail($import));
    }

    public function download(string $import): Response
    {
        $model = Import::findOrFail($import);

        return response($model->rejection_report ?? "reason\n", 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="product-import-'.$model->getKey().'-rejections.csv"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
