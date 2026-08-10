import { Form, Head } from '@inertiajs/react';
import AuthField from '@/components/auth-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { update } from '@/routes/password';

type Props = {
    token: string;
    email: string;
    passwordRules: string;
};

export default function ResetPassword({ token, email, passwordRules }: Props) {
    return (
        <>
            <Head title="Reset password" />

            <Form
                {...update.form()}
                transform={(data) => ({ ...data, token, email })}
                resetOnSuccess={['password', 'password_confirmation']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4">
                            <AuthField
                                label="Email address"
                                type="email"
                                name="email"
                                autoComplete="email"
                                value={email}
                                error={errors.email}
                                readOnly
                            />

                            <AuthField
                                label="Password"
                                type="password"
                                name="password"
                                placeholder="Password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                error={errors.password}
                                autoFocus
                            />

                            <AuthField
                                label="Confirm password"
                                type="password"
                                name="password_confirmation"
                                placeholder="Confirm password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                error={errors.password_confirmation}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="h-12 w-full rounded-lg"
                            disabled={processing}
                            data-test="reset-password-button"
                        >
                            {processing && <Spinner />}
                            Reset password
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

ResetPassword.layout = {
    title: 'Reset password',
    description: 'Please enter your new password below',
};
