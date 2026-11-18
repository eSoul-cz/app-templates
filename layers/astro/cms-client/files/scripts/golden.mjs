// Path and reader of the backend golden export: the real output of `ExportContentQuery` over test data.
import { readFile } from 'node:fs/promises';

export const goldenPath = new URL('../../backend/tests/Fixtures/content-export.golden.json', import.meta.url);

export async function readGolden() {
    try {
        return JSON.parse(await readFile(goldenPath, 'utf8'));
    } catch (error) {
        throw new Error(
            `Cannot read the backend golden export (${goldenPath.pathname}): ${error.message}. Generate it: cd backend && GOLDEN_UPDATE=1 php artisan test --filter=GoldenExportTest`,
        );
    }
}

/** Walks the export and calls `visit` on every file object (`url` + `sha256`). */
export function eachFile(node, visit) {
    if (Array.isArray(node)) node.forEach((child) => eachFile(child, visit));
    else if (node && typeof node === 'object') {
        if (typeof node.url === 'string' && typeof node.sha256 === 'string') visit(node);
        Object.values(node).forEach((child) => eachFile(child, visit));
    }
}
