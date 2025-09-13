import React, { useEffect, useMemo, useRef } from 'react';
import { cn } from '@/lib/utils';

export type InputOTPProps = {
  length?: number;
  value: string;
  onChange: (val: string) => void;
  autoFocus?: boolean;
  disabled?: boolean;
  className?: string;
};

export function InputOTP({ length = 6, value, onChange, autoFocus, disabled, className }: InputOTPProps) {
  const refs = useRef<HTMLInputElement[]>([]);
  const chars = useMemo(() => Array.from({ length }, (_, i) => value[i] ?? ''), [length, value]);

  useEffect(() => {
    if (autoFocus && refs.current[0]) {
      refs.current[0].focus();
      refs.current[0].select?.();
    }
  }, [autoFocus]);

  function setCharAt(pos: number, c: string) {
    const next = (value.substring(0, pos) + c + value.substring(pos + 1)).slice(0, length);
    onChange(next);
  }

  function handleChange(e: React.ChangeEvent<HTMLInputElement>, idx: number) {
    const raw = e.target.value.replace(/\D/g, '');
    if (!raw) {
      setCharAt(idx, '');
      return;
    }
    // allow pasting multiple digits
    const digits = raw.slice(0, length - idx).split('');
    const next = value.split('');
    digits.forEach((d, i) => {
      next[idx + i] = d;
    });
    const joined = next.join('').slice(0, length);
    onChange(joined);
    const nextIndex = Math.min(idx + digits.length, length - 1);
    refs.current[nextIndex]?.focus();
    refs.current[nextIndex]?.select?.();
  }

  function handleKeyDown(e: React.KeyboardEvent<HTMLInputElement>, idx: number) {
    if (e.key === 'Backspace' && !chars[idx] && idx > 0) {
      e.preventDefault();
      const prev = idx - 1;
      setCharAt(prev, '');
      refs.current[prev]?.focus();
      refs.current[prev]?.select?.();
    } else if (e.key === 'ArrowLeft' && idx > 0) {
      e.preventDefault();
      refs.current[idx - 1]?.focus();
    } else if (e.key === 'ArrowRight' && idx < length - 1) {
      e.preventDefault();
      refs.current[idx + 1]?.focus();
    }
  }

  function handlePaste(e: React.ClipboardEvent<HTMLInputElement>, idx: number) {
    const text = e.clipboardData.getData('text').replace(/\D/g, '');
    if (!text) return;
    e.preventDefault();
    const digits = text.slice(0, length - idx).split('');
    const next = value.split('');
    digits.forEach((d, i) => (next[idx + i] = d));
    onChange(next.join(''));
    const nextIndex = Math.min(idx + digits.length, length - 1);
    refs.current[nextIndex]?.focus();
    refs.current[nextIndex]?.select?.();
  }

  return (
    <div className={cn('flex items-center gap-2', className)}>
      {Array.from({ length }).map((_, i) => (
        <input
          key={i}
          ref={(el) => {
            if (el) refs.current[i] = el;
          }}
          type="text"
          inputMode="numeric"
          pattern="[0-9]*"
          maxLength={1}
          className={cn(
            'h-10 w-10 rounded-md border bg-background text-center text-base font-medium outline-none ring-offset-background transition focus:border-ring focus:ring-2 focus:ring-ring/50',
            disabled && 'opacity-50',
          )}
          value={chars[i]}
          onChange={(e) => handleChange(e, i)}
          onKeyDown={(e) => handleKeyDown(e, i)}
          onPaste={(e) => handlePaste(e, i)}
          disabled={disabled}
        />
      ))}
    </div>
  );
}
