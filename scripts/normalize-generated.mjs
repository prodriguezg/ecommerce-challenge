import { readFileSync, writeFileSync } from 'node:fs'

const generatedDocumentation = 'services/commerce-api/public/api-docs.html'
const content = readFileSync(generatedDocumentation, 'utf8')

writeFileSync(generatedDocumentation, content.replace(/[\t ]+$/gm, ''))
