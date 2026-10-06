import { existsSync, readdirSync, readFileSync, statSync } from 'node:fs'
import { dirname, extname, join, relative, resolve } from 'node:path'

const root = resolve(import.meta.dirname, '..')
const documentation = [join(root, 'README.md')]

function collectMarkdown(directory) {
  for (const entry of readdirSync(directory)) {
    const path = join(directory, entry)
    if (statSync(path).isDirectory()) collectMarkdown(path)
    else if (extname(path) === '.md') documentation.push(path)
  }
}

collectMarkdown(join(root, 'docs'))

const failures = []
const markdownLink = /!?(?:\[[^\]]*\])\(([^)]+)\)/g

function anchors(path) {
  const seen = new Map()
  const values = new Set()
  for (const line of readFileSync(path, 'utf8').split('\n')) {
    const heading = line.match(/^#{1,6}\s+(.+?)\s*#*$/)
    if (!heading) continue
    const base = heading[1]
      .replace(/<[^>]+>/g, '')
      .replace(/[`*_~]/g, '')
      .toLowerCase()
      .replace(/[^\p{Letter}\p{Number}\s_-]/gu, '')
      .trim()
      .replace(/\s+/g, '-')
    const duplicate = seen.get(base) ?? 0
    seen.set(base, duplicate + 1)
    values.add(duplicate === 0 ? base : `${base}-${duplicate}`)
  }
  return values
}

for (const source of documentation) {
  const content = readFileSync(source, 'utf8')
  for (const match of content.matchAll(markdownLink)) {
    const link = match[1].trim().replace(/^<|>$/g, '')
    if (/^(?:https?:|mailto:)/.test(link)) continue
    const hash = link.indexOf('#')
    const destination = hash === -1 ? link : link.slice(0, hash)
    const fragment = hash === -1 ? '' : decodeURIComponent(link.slice(hash + 1))

    const target = destination
      ? resolve(dirname(source), decodeURIComponent(destination))
      : source
    if (!existsSync(target)) {
      failures.push(`${relative(root, source)} -> ${link}`)
    } else if (fragment && extname(target) === '.md' && !anchors(target).has(fragment)) {
      failures.push(`${relative(root, source)} -> ${link} (missing heading)`)
    }
  }
}

if (failures.length > 0) {
  console.error('Broken local documentation links:')
  failures.forEach((failure) => console.error(`- ${failure}`))
  process.exit(1)
}

console.log(`Checked ${documentation.length} Markdown files; all local links resolve.`)
