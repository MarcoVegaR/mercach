import { FileDropzone } from '@/components/ui/file-dropzone';
import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

describe('FileDropzone', () => {
    it('previews an AVIF file when its URL contains a temporary signature', () => {
        const temporaryUrl = 'https://private.r2.test/photo.avif?X-Amz-Signature=signed';

        render(
            <FileDropzone
                onFileSelect={vi.fn()}
                existingFileUrl={temporaryUrl}
                existingFileName="photo.avif"
                accept="image/png,image/jpeg,image/avif"
                preview
            />,
        );

        expect(screen.getByRole('img', { name: 'Preview' })).toHaveAttribute('src', temporaryUrl);
        expect(screen.getByRole('link', { name: 'Ver' })).toHaveAttribute('href', temporaryUrl);
    });
});
