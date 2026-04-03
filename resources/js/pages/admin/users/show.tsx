import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';
import {
    index,
    sendPasswordResetLink,
} from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import type { User } from '@/types';

export default function UserShow({ user }: { user: User }) {
    const [showResetDialog, setShowResetDialog] = useState(false);
    const flash = usePage<{ props: { flash?: { success?: string } } }>().props
        .flash as { success?: string } | undefined;

    return (
        <>
            <Head title={user.name} />

            <div className="px-4 py-6">
                <Heading title={user.name} description="User details" />

                {flash?.success && (
                    <div className="mb-4 rounded-md border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}

                <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <dl className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                        <div className="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-muted-foreground">
                                Name
                            </dt>
                            <dd className="mt-1 text-sm sm:col-span-2 sm:mt-0">
                                {user.name}
                            </dd>
                        </div>

                        <div className="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-muted-foreground">
                                Handle
                            </dt>
                            <dd className="mt-1 text-sm sm:col-span-2 sm:mt-0">
                                {user.handle}
                            </dd>
                        </div>

                        <div className="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-muted-foreground">
                                Email
                            </dt>
                            <dd className="mt-1 text-sm sm:col-span-2 sm:mt-0">
                                {user.email}
                            </dd>
                        </div>

                        <div className="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-muted-foreground">
                                Role
                            </dt>
                            <dd className="mt-1 text-sm sm:col-span-2 sm:mt-0">
                                {user.is_admin ? (
                                    <Badge variant="default">Admin</Badge>
                                ) : (
                                    <Badge variant="secondary">User</Badge>
                                )}
                            </dd>
                        </div>

                        <div className="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-muted-foreground">
                                Email verified
                            </dt>
                            <dd className="mt-1 text-sm sm:col-span-2 sm:mt-0">
                                {user.email_verified_at
                                    ? new Date(
                                          user.email_verified_at,
                                      ).toLocaleDateString()
                                    : 'Not verified'}
                            </dd>
                        </div>

                        <div className="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-muted-foreground">
                                Joined
                            </dt>
                            <dd className="mt-1 text-sm sm:col-span-2 sm:mt-0">
                                {new Date(
                                    user.created_at,
                                ).toLocaleDateString()}
                            </dd>
                        </div>

                        <div className="px-4 py-4 sm:grid sm:grid-cols-3 sm:gap-4">
                            <dt className="text-sm font-medium text-muted-foreground">
                                Last updated
                            </dt>
                            <dd className="mt-1 text-sm sm:col-span-2 sm:mt-0">
                                {new Date(
                                    user.updated_at,
                                ).toLocaleDateString()}
                            </dd>
                        </div>
                    </dl>
                </div>

                <div className="mt-6">
                    <Button
                        variant="secondary"
                        onClick={() => setShowResetDialog(true)}
                    >
                        Send password reset link
                    </Button>
                </div>
            </div>

            <Dialog
                open={showResetDialog}
                onOpenChange={(open) => {
                    if (!open) setShowResetDialog(false);
                }}
            >
                <DialogContent>
                    <DialogTitle>Send password reset link</DialogTitle>
                    <DialogDescription>
                        This will send a password reset link to{' '}
                        <strong>{user.email}</strong>. The user will be able to
                        set a new password using the link.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        <Link
                            href={sendPasswordResetLink(user.id).url}
                            method="post"
                            as="button"
                            preserveScroll
                            onSuccess={() => setShowResetDialog(false)}
                            className="inline-flex items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-xs hover:bg-primary/90"
                        >
                            Send reset link
                        </Link>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

UserShow.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: index.url(),
        },
        {
            title: 'User details',
        },
    ],
};
