import { Form, Head } from '@inertiajs/react';
import AuthField from '@/components/auth-field';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { register } from '@/routes';
import { store } from '@/routes/login';
import { request } from '@/routes/password';

type Props = {
    status?: string;
    canResetPassword: boolean;
};

export default function Login({ status, canResetPassword }: Props) {
    return (
        <>
            <Head title="Log in" />

            {status && (
                <div className="mb-6 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}

            <Form
                {...store.form()}
                resetOnSuccess={['password']}
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4">
                            <AuthField
                                label="Email address"
                                type="email"
                                name="email"
                                placeholder="Email address"
                                autoComplete="email"
                                error={errors.email}
                                required
                                autoFocus
                                tabIndex={1}
                            />

                            <AuthField
                                label="Password"
                                type="password"
                                name="password"
                                placeholder="Password"
                                autoComplete="current-password"
                                error={errors.password}
                                required
                                tabIndex={2}
                            />

                            <div className="flex items-center justify-between">
                                <div className="flex items-center space-x-3">
                                    <Checkbox
                                        id="remember"
                                        name="remember"
                                        tabIndex={3}
                                    />
                                    <Label htmlFor="remember">
                                        Remember me
                                    </Label>
                                </div>

                                {canResetPassword && (
                                    <TextLink
                                        href={request()}
                                        className="text-sm"
                                        tabIndex={4}
                                    >
                                        Forgot password?
                                    </TextLink>
                                )}
                            </div>
                        </div>

                        <Button
                            type="submit"
                            className="w-full"
                            tabIndex={5}
                            disabled={processing}
                            data-test="login-button"
                        >
                            {processing && <Spinner />}
                            Log in
                        </Button>

                        <div className="text-center text-sm text-muted-foreground">
                            Don't have an account?{' '}
                            <TextLink
                                href={register()}
                                data-test="register-link"
                                tabIndex={6}
                            >
                                Sign up
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Login.layout = {
    title: 'Log in to your account',
    description: 'Enter your email and password below to log in',
};
