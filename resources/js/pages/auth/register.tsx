import { Form, Head } from '@inertiajs/react';
import AuthField from '@/components/auth-field';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

type Props = {
    passwordRules: string;
};

export default function Register({ passwordRules }: Props) {
    return (
        <>
            <Head title="Register" />

            <Form
                {...store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4">
                            <AuthField
                                label="Full name"
                                name="name"
                                placeholder="Full name"
                                autoComplete="name"
                                error={errors.name}
                                required
                                autoFocus
                                tabIndex={1}
                            />

                            <AuthField
                                label="Email address"
                                type="email"
                                name="email"
                                placeholder="Email address"
                                autoComplete="email"
                                error={errors.email}
                                required
                                tabIndex={2}
                            />

                            <AuthField
                                label="Password"
                                type="password"
                                name="password"
                                placeholder="Password"
                                autoComplete="new-password"
                                passwordrules={passwordRules}
                                error={errors.password}
                                required
                                tabIndex={3}
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
                                tabIndex={4}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="h-12 w-full rounded-lg"
                            tabIndex={5}
                            data-test="register-user-button"
                        >
                            {processing && <Spinner />}
                            Create account
                        </Button>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={6}>
                                Log in
                            </TextLink>
                        </div>
                    </>
                )}
            </Form>
        </>
    );
}

Register.layout = {
    title: 'Create an account',
    description: 'Enter your details below to create your account',
};
