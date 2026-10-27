import {readFile, writeFile} from 'node:fs/promises';

const source = new URL('../libs/z2m/src/extension.mjs', import.meta.url);
const schema = new URL('../libs/z2m/automations.schema.json', import.meta.url);
const target = new URL('../libs/z2m/z2m-automations.mjs', import.meta.url);
const marker = "import AUTOMATIONS_SCHEMA from '../automations.schema.json' with {type: 'json'};";
const code = await readFile(source, 'utf8');
if (code.split(marker).length !== 2) throw new Error('Expected exactly one schema import.');
const generated = '// Generated from src/extension.mjs and automations.schema.json. Do not edit.\n'
    + code.replace(marker, 'const AUTOMATIONS_SCHEMA = ' + JSON.stringify(JSON.parse(await readFile(schema, 'utf8'))) + ';');
if (process.argv.includes('--check')) {
    if (await readFile(target, 'utf8') !== generated) throw new Error('Extension artifact is stale. Run npm run build:extension.');
    console.log('Extension artifact matches source and schema.');
} else {
    await writeFile(target, generated, 'utf8');
    console.log('Built single-file Z2M extension with embedded schema.');
}
