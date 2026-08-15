import { Form, Head } from '@inertiajs/react';
import AuthField from '@/components/auth-field';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import { store } from '@/routes/register';

export default function Register() {
    return (
        <>
            <Head title="Register" />

            <Form
                {...store.form()}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-4">
                            <AuthField
                                label="Registration number"
                                name="reg_number"
                                placeholder="Registration number, e.g. 2021/000123"
                                autoComplete="off"
                                autoCapitalize="off"
                                autoCorrect="off"
                                error={errors.reg_number}
                                required
                                autoFocus
                                tabIndex={1}
                            />

                            <AuthField
                                label="Email address"
                                type="email"
                                name="email"
                                placeholder="Email address you can open now"
                                autoComplete="email"
                                error={errors.email}
                                required
                                tabIndex={2}
                            />
                        </div>

                        <Button
                            type="submit"
                            className="h-12 w-full rounded-lg"
                            tabIndex={3}
                            data-test="register-user-button"
                        >
                            {processing && <Spinner />}
                            Send verification code
                        </Button>

                        <div className="text-center text-sm text-muted-foreground">
                            Already have an account?{' '}
                            <TextLink href={login()} tabIndex={4}>
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
    title: 'Claim your student account',
    description:
        'Your department has already registered you. Enter the registration number on your student ID and we will email you a code.',
};
