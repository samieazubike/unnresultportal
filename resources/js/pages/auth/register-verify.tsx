import { Form, Head, setLayoutProps } from '@inertiajs/react';
import { REGEXP_ONLY_DIGITS } from 'input-otp';
import { useState } from 'react';
import InputError from '@/components/input-error';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import {
    InputOTP,
    InputOTPGroup,
    InputOTPSlot,
} from '@/components/ui/input-otp';
import { Spinner } from '@/components/ui/spinner';
import { OTP_MAX_LENGTH } from '@/hooks/use-two-factor-auth';
import { register } from '@/routes';
import { resend, store } from '@/routes/register/verify';

type Props = {
    email: string;
    regNumber: string;
    expiresInMinutes: number;
    status?: string;
};

export default function RegisterVerify({
    email,
    regNumber,
    expiresInMinutes,
    status,
}: Props) {
    const [code, setCode] = useState<string>('');

    setLayoutProps({
        title: 'Enter your verification code',
        description: `We sent a six-digit code to ${email}.`,
    });

    return (
        <>
            <Head title="Verify your email" />

            {status && (
                <div className="mb-6 text-center text-sm font-medium text-success">
                    {status}
                </div>
            )}

            <div className="flex flex-col gap-6">
                <Form
                    {...store.form()}
                    resetOnError
                    className="flex flex-col gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="flex flex-col items-center justify-center gap-3 text-center">
                                <div className="flex w-full items-center justify-center">
                                    <InputOTP
                                        name="code"
                                        maxLength={OTP_MAX_LENGTH}
                                        value={code}
                                        onChange={(value) => setCode(value)}
                                        disabled={processing}
                                        pattern={REGEXP_ONLY_DIGITS}
                                        autoFocus
                                    >
                                        <InputOTPGroup>
                                            {Array.from(
                                                { length: OTP_MAX_LENGTH },
                                                (_, index) => (
                                                    <InputOTPSlot
                                                        key={index}
                                                        index={index}
                                                    />
                                                ),
                                            )}
                                        </InputOTPGroup>
                                    </InputOTP>
                                </div>
                                <InputError message={errors.code} />
                            </div>

                            <Button
                                type="submit"
                                className="w-full"
                                disabled={processing}
                                data-test="verify-code-button"
                            >
                                {processing && <Spinner />}
                                Continue
                            </Button>
                        </>
                    )}
                </Form>

                <div className="flex flex-col gap-2 text-center text-sm text-muted-foreground">
                    <p>
                        The code expires in {expiresInMinutes} minutes. Claiming{' '}
                        <span className="font-medium text-foreground">
                            {regNumber}
                        </span>
                        .
                    </p>

                    <Form {...resend.form()} options={{ preserveScroll: true }}>
                        {({ processing }) => (
                            <p>
                                Didn't get it?{' '}
                                <button
                                    type="submit"
                                    disabled={processing}
                                    className="cursor-pointer text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! disabled:opacity-60 dark:decoration-neutral-500"
                                >
                                    Send a new code
                                </button>
                            </p>
                        )}
                    </Form>

                    <p>
                        <TextLink href={register()}>
                            Use a different registration number
                        </TextLink>
                    </p>
                </div>
            </div>
        </>
    );
}
