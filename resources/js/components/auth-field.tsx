import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';

/**
 * Taller and softer than the application default, because an auth screen holds
 * three fields rather than thirty. `rounded-lg` is `--radius`, so this stays on
 * the token system.
 */
const AUTH_INPUT_CLASS = 'h-12 rounded-lg px-4';

type AuthFieldProps = Omit<ComponentProps<'input'>, 'type'> & {
    /** Rendered for screen readers only; sighted users get the placeholder. */
    label: string;
    error?: string;
    type?: 'text' | 'email' | 'password';
};

/**
 * One field on an auth form: a hidden label, the input, and its error.
 *
 * The auth screens are labelled by placeholder, so the label is `sr-only`
 * rather than absent — the field still has an accessible name, and `htmlFor`
 * still points at a real control.
 */
export default function AuthField({
    label,
    error,
    type = 'text',
    className,
    id,
    name,
    ...props
}: AuthFieldProps) {
    const fieldId = id ?? name;
    const inputClassName = cn(AUTH_INPUT_CLASS, className);

    return (
        <div className="grid gap-2">
            <Label htmlFor={fieldId} className="sr-only">
                {label}
            </Label>

            {type === 'password' ? (
                <PasswordInput
                    id={fieldId}
                    name={name}
                    className={inputClassName}
                    aria-invalid={Boolean(error)}
                    {...props}
                />
            ) : (
                <Input
                    id={fieldId}
                    name={name}
                    type={type}
                    className={inputClassName}
                    aria-invalid={Boolean(error)}
                    {...props}
                />
            )}

            <InputError message={error} />
        </div>
    );
}
