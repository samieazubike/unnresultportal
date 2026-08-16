import type { ComponentProps } from 'react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';

type AuthFieldProps = Omit<ComponentProps<'input'>, 'type'> & {
    label: string;
    error?: string;
    type?: 'text' | 'email' | 'password';
};

/**
 * One field on an auth form: its label, the input, and its error.
 *
 * The label is visible rather than `sr-only`. Sera's input is a rule under the
 * text with no box around it, so a placeholder alone leaves nothing to read
 * once the field is filled — the uppercase label is what names it.
 *
 * No sizing or radius overrides live here: height, padding and the underline
 * all belong to the Input primitive.
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

    return (
        <div className="grid gap-1.5">
            <Label htmlFor={fieldId}>{label}</Label>

            {type === 'password' ? (
                <PasswordInput
                    id={fieldId}
                    name={name}
                    className={className}
                    aria-invalid={Boolean(error)}
                    {...props}
                />
            ) : (
                <Input
                    id={fieldId}
                    name={name}
                    type={type}
                    className={className}
                    aria-invalid={Boolean(error)}
                    {...props}
                />
            )}

            <InputError message={error} />
        </div>
    );
}
