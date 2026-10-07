import { readFileSync, writeFileSync } from 'node:fs'

const generatedDocumentation = 'services/commerce-api/public/api-docs.html'
const content = readFileSync(generatedDocumentation, 'utf8')
const containerStart = '<div id="redoc">'
const stateStart = '</div>\n      <script>\n      const __redoc_state'
const containerIndex = content.indexOf(containerStart)
const stateIndex = content.lastIndexOf(stateStart)

if (containerIndex === -1 || stateIndex === -1 || stateIndex <= containerIndex) {
  throw new Error('Could not locate the generated Redoc container and state.')
}

const clientRenderedDocumentation = [
  content.slice(0, containerIndex + containerStart.length),
  content.slice(stateIndex),
].join('')

if (!clientRenderedDocumentation.includes('Redoc.hydrate(__redoc_state, container);')) {
  throw new Error('Could not locate the generated Redoc hydration call.')
}

writeFileSync(
  generatedDocumentation,
  clientRenderedDocumentation
    .replace('Redoc.hydrate(__redoc_state, container);', 'Redoc.init(__redoc_state.spec.data, {}, container);')
    .replace(/[\t ]+$/gm, ''),
)
