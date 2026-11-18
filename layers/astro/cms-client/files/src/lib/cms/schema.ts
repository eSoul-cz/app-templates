// Export contract v1 from the backend (GET /cms-api/v1/export), see docs/contract.md.
// Change it in the backend (exporter + golden test), here and in the contract in ONE pull request:
// the build fails on a mismatch on purpose.
import { z } from 'astro/zod';

/** Same rule as the backend (`ContentExport::SLUG_PATTERN`): the slug is a path segment of the page URL. */
const SLUG_PATTERN = /^[a-z0-9]+(-[a-z0-9]+)*$/;

/**
 * File URL: `/_cms/<sha256[0:20]>.<ext>` from the backend (the file is already on the site, media.ts checks it),
 * a relative path without scheme and without a leading `/` from the fixture. No `javascript:`, `data:`, `//host`.
 */
const fileUrlSchema = z
    .string()
    .min(1)
    .refine((url) => /^\/_cms\/[a-f0-9]{20}\.[a-z0-9]+$/.test(url) || (!/^[a-z][a-z0-9+.-]*:/i.test(url) && !url.startsWith('/')), {
        message: 'File URL must be /_cms/<hash>.<ext> (backend) or a relative path (fixture).',
    });

/** sha256 of empty content: a failed upload must never reach the site. */
const EMPTY_SHA256 = 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855';

const sha256Schema = z
    .string()
    .regex(/^[a-f0-9]{64}$/)
    .refine((hash) => hash !== EMPTY_SHA256, { message: 'Empty file: re-upload it in the admin.' });

export const fileSchema = z.object({
    url: fileUrlSchema,
    mime: z.string(),
    bytes: z.number().int().positive(),
    sha256: sha256Schema,
});

export const imageSchema = fileSchema.extend({
    width: z.number().int().positive().nullable(),
    height: z.number().int().positive().nullable(),
});

export const blockSchema = z.discriminatedUnion('type', [
    z.object({ type: z.literal('heading'), level: z.union([z.literal(2), z.literal(3)]), text: z.string().min(1) }),
    /** Plain text; blank lines separate paragraphs. Never HTML. */
    z.object({ type: z.literal('text'), body: z.string().min(1) }),
    z.object({ type: z.literal('image'), image: imageSchema, alt: z.string().min(1), caption: z.string().nullable() }),
]);

export const pageSchema = z.object({
    id: z.number().int(),
    slug: z.string().regex(SLUG_PATTERN, { message: 'Slug may contain only a-z, 0-9 and single hyphens.' }),
    /** The home page is rendered at `/`; its slug is only an identifier. */
    isHome: z.boolean(),
    sort: z.number().int(),
    title: z.string().min(1),
    description: z.string().nullable(),
    blocks: z.array(blockSchema),
});

export const settingsSchema = z.object({
    siteName: z.string().min(1),
    description: z.string().nullable(),
    contactEmail: z.email({ message: 'Contact e-mail is not a valid address.' }).nullable(),
    footerText: z.string().nullable(),
    ogImage: imageSchema.nullable(),
});

export const exportSchema = z
    .object({
        schemaVersion: z.literal(1),
        generatedAt: z.string(),
        contentHash: z.string(),
        settings: settingsSchema,
        pages: z.array(pageSchema),
    })
    .superRefine((data, ctx) => {
        const issue = (message: string) => ctx.addIssue({ code: 'custom', message });

        if (data.pages.filter((page) => page.isHome).length !== 1) issue('Export must contain exactly one home page.');

        const duplicates = <T extends string | number>(label: string, values: T[]) => {
            for (const value of new Set(values)) {
                if (values.filter((candidate) => candidate === value).length > 1) issue(`Duplicate ${label}: ${value}`);
            }
        };
        duplicates(
            'page id',
            data.pages.map((page) => page.id),
        );
        duplicates(
            'page slug',
            data.pages.map((page) => page.slug),
        );
    });

export type CmsFile = z.infer<typeof fileSchema>;
export type CmsImage = z.infer<typeof imageSchema>;
export type CmsBlock = z.infer<typeof blockSchema>;
export type CmsPage = z.infer<typeof pageSchema>;
export type CmsSettings = z.infer<typeof settingsSchema>;
export type CmsExport = z.infer<typeof exportSchema>;
