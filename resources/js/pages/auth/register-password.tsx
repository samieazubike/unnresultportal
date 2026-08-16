import { Form, Head } from '@inertiajs/react';
import AuthField from '@/components/auth-field';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { store } from '@/routes/register/password';

type Props = {
    name: string;
    regNumber: string;
    email: string;
    passwordRules: string;
};

export default function RegisterPassword({
    name,
    regNumber,
    email,
    passwordRules,
}: Props) {
    return (
        <>
            <Head title="Set your password" />

            <Form
                {...store.form()}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <dl className="grid gap-2 rounded-lg border bg-muted/40 p-4 text-sm">
                            <div className="flex items-center justify-between gap-4">
                                <dt className="text-muted-foreground">Name</dt>
                                <dd className="font-medium">{name}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-4">
                                <dt className="text-muted-foreground">
                                    Registration number
                                </dt>
                                <dd className="font-medium">{regNumber}</dd>
                            </div>
                            <div className="flex items-center justify-between gap-4">
                                <dt className="text-muted-foreground">Email</dt>
                                <dd className="font-medium">{email}</dd>
                            </div>
                        </dl>

                        <div className="grid gap-4">
                            <AuthField
                                label="Password"
                                type="password"
                                name="password"
                                placeholder="Password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                error={errors.password}
                                required
                                autoFocus
                                tabIndex={1}
                            />

                            <AuthField
                                label="Confirm password"
                                type="password"
                                name="password_confirmation"
                                placeholder="Confirm password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                error={errors.password_confirmation}
                                required
                                tabIndex={2}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            tabIndex={3}
                            data-test="set-password-button"
                        >
                            {processing && <Spinner />}
                            Create account
                        </Button>
                    </>
                )}
            </Form>
        </>
    );
}

RegisterPassword.layout = {
    title: 'Set your password',
    description: 'Last step — choose a password and your account is ready.',
};
