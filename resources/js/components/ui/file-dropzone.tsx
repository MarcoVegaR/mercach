import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Upload, X, Eye, File } from 'lucide-react';
import { forwardRef, useCallback } from 'react';

export interface FileDropzoneProps {
  onFileSelect: (file: File | null) => void;
  file?: File | null;
  existingFileUrl?: string;
  existingFileName?: string;
  accept?: string;
  maxSize?: string;
  preview?: boolean;
  className?: string;
  placeholder?: string;
  disabled?: boolean;
}

const FileDropzone = forwardRef<HTMLInputElement, FileDropzoneProps>(
  ({
    onFileSelect,
    file,
    existingFileUrl,
    existingFileName,
    accept = '*',
    maxSize = '5 MB',
    preview = false,
    className,
    placeholder = 'Seleccionar archivo',
    disabled = false,
  }, ref) => {
    const handleFileChange = useCallback((e: React.ChangeEvent<HTMLInputElement>) => {
      const selectedFile = e.target.files?.[0] || null;
      onFileSelect(selectedFile);
    }, [onFileSelect]);

    const handleRemove = useCallback(() => {
      onFileSelect(null);
      if (ref && 'current' in ref && ref.current) {
        ref.current.value = '';
      }
    }, [onFileSelect, ref]);

    const hasFile = !!file;
    const hasExisting = !file && (!!existingFileUrl || !!existingFileName);
    const isImage = file?.type.startsWith('image/') || existingFileUrl?.match(/\.(jpg|jpeg|png|gif|webp)$/i);
    const displayName = file?.name || existingFileName || 'Archivo';

    return (
      <div className={cn('space-y-3', className)}>
        {/* File Input */}
        <input
          ref={ref}
          type="file"
          accept={accept}
          onChange={handleFileChange}
          className="sr-only"
          disabled={disabled}
        />

        {/* Dropzone Area */}
        <div 
          className={cn(
            'relative rounded-lg border-2 border-dashed transition-colors',
            hasFile || hasExisting 
              ? 'border-green-200 bg-green-50 dark:border-green-900 dark:bg-green-950'
              : 'border-muted-foreground/25 bg-muted/20 hover:bg-muted/30',
            disabled && 'opacity-50 cursor-not-allowed'
          )}
        >
          <div className="p-4">
            <div className="flex items-center gap-4">
              {/* Preview or Icon */}
              <div className="flex-shrink-0">
                {preview && isImage && (file || existingFileUrl) ? (
                  <div className="h-12 w-12 rounded-md border overflow-hidden bg-muted">
                    <img
                      src={file ? URL.createObjectURL(file) : existingFileUrl}
                      alt="Preview"
                      className="h-full w-full object-cover"
                    />
                  </div>
                ) : (
                  <div className={cn(
                    'flex h-12 w-12 items-center justify-center rounded-md border',
                    hasFile || hasExisting 
                      ? 'border-green-200 bg-green-100 text-green-700 dark:border-green-800 dark:bg-green-900 dark:text-green-300'
                      : 'border-muted bg-muted/50 text-muted-foreground'
                  )}>
                    {hasFile || hasExisting ? (
                      <File className="h-5 w-5" />
                    ) : (
                      <Upload className="h-5 w-5" />
                    )}
                  </div>
                )}
              </div>

              {/* Content */}
              <div className="flex-1 min-w-0">
                {hasFile || hasExisting ? (
                  <div className="space-y-1">
                    <p className="text-sm font-medium truncate" title={displayName}>
                      {displayName}
                    </p>
                    <div className="flex items-center gap-3 text-xs text-muted-foreground">
                      {hasExisting && existingFileUrl && (
                        <a
                          href={existingFileUrl}
                          target="_blank"
                          rel="noopener noreferrer"
                          className="inline-flex items-center gap-1 text-blue-600 hover:text-blue-700 dark:text-blue-400"
                        >
                          <Eye className="h-3 w-3" />
                          Ver
                        </a>
                      )}
                    </div>
                  </div>
                ) : (
                  <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                      Haz clic para seleccionar archivo
                    </p>
                    <p className="text-xs text-muted-foreground">
                      Máx. {maxSize}
                    </p>
                  </div>
                )}
              </div>

              {/* Actions */}
              <div className="flex items-center gap-2">
                <Button
                  type="button"
                  variant="outline"
                  size="sm"
                  onClick={() => {
                    if (ref && 'current' in ref && ref.current) {
                      ref.current.click();
                    }
                  }}
                  disabled={disabled}
                >
                  <Upload className="h-3 w-3 mr-1" />
                  {hasFile || hasExisting ? 'Cambiar' : placeholder}
                </Button>
                {(hasFile || hasExisting) && (
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={handleRemove}
                    disabled={disabled}
                    className="text-destructive border-destructive/20 hover:bg-destructive/10"
                  >
                    <X className="h-3 w-3" />
                  </Button>
                )}
              </div>
            </div>
          </div>
        </div>
      </div>
    );
  }
);

FileDropzone.displayName = 'FileDropzone';

export { FileDropzone };
