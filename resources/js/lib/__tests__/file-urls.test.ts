import { resolveStoredFileUrl } from '@/lib/file-urls';
import { describe, expect, it } from 'vitest';

describe('resolveStoredFileUrl', () => {
    it('prefers a temporary remote URL over the stored path', () => {
        const temporaryUrl = 'https://private.r2.test/photo.avif?X-Amz-Signature=signed';

        expect(resolveStoredFileUrl(temporaryUrl, 'concessionaires/photos/photo.avif')).toBe(temporaryUrl);
    });

    it('falls back to local storage when no remote URL is available', () => {
        expect(resolveStoredFileUrl(null, 'concessionaires/photos/photo.png')).toBe('/storage/concessionaires/photos/photo.png');
    });

    it('returns undefined when no file is stored', () => {
        expect(resolveStoredFileUrl(null, null)).toBeUndefined();
    });
});
