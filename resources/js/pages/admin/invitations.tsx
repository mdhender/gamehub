import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import InvitationController, {
    destroy,
    resend,
    store,
} from '@/actions/App/Http/Controllers/Admin/InvitationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

type Invitation = {
    id: number;
    email: string;
    expires_at: string;
    registered_at: string | null;
    created_at: string;
};

function invitationStatus(invitation: Invitation) {
    if (invitation.registered_at) {
        return <Badge variant="default">Registered</Badge>;
    }

    if (new Date(invitation.expires_at) < new Date()) {
        return <Badge variant="destructive">Expired</Badge>;
    }

    return <Badge variant="secondary">Pending</Badge>;
}

export default function AdminInvitations({
    invitations,
}: {
    invitations: Invitation[];
}) {
    const [deletingInvitation, setDeletingInvitation] =
        useState<Invitation | null>(null);

    return (
        <>
            <Head title="Invitations" />

            <div className="px-4 py-6">
                <Heading
                    title="Invitations"
                    description="Invite users to register"
                />

                <Form
                    {...store.form()}
                    resetOnSuccess
                    disableWhileProcessing
                    className="mb-8 flex items-end gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid flex-1 gap-2">
                                <Label htmlFor="email">Email address</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    name="email"
                                    required
                                    placeholder="email@example.com"
                                />
                                <InputError message={errors.email} />
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing && <Spinner />}
                                Send invitation
                            </Button>
                        </>
                    )}
                </Form>

                <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Email
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Sent
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Expires
                                </th>
                                <th className="px-4 py-3 text-right font-medium">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {invitations.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No invitations sent yet.
                                    </td>
                                </tr>
                            )}
                            {invitations.map((invitation) => (
                                <tr
                                    key={invitation.id}
                                    className="hover:bg-muted/30"
                                >
                                    <td className="px-4 py-3">
                                        {invitation.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        {invitationStatus(invitation)}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {new Date(
                                            invitation.created_at,
                                        ).toLocaleDateString()}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {new Date(
                                            invitation.expires_at,
                                        ).toLocaleDateString()}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <div className="flex items-center justify-end gap-2">
                                            {!invitation.registered_at && (
                                                <Link
                                                    href={
                                                        resend(invitation.id)
                                                            .url
                                                    }
                                                    method="post"
                                                    as="button"
                                                    className="text-sm text-muted-foreground underline-offset-4 hover:underline"
                                                >
                                                    Resend
                                                </Link>
                                            )}
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                className="text-destructive hover:text-destructive"
                                                onClick={() =>
                                                    setDeletingInvitation(
                                                        invitation,
                                                    )
                                                }
                                            >
                                                Delete
                                            </Button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>

            <Dialog
                open={deletingInvitation !== null}
                onOpenChange={(open) => {
                    if (!open) setDeletingInvitation(null);
                }}
            >
                <DialogContent>
                    <DialogTitle>Delete invitation</DialogTitle>
                    <DialogDescription>
                        Are you sure you want to delete the invitation for{' '}
                        <strong>{deletingInvitation?.email}</strong>? This action
                        cannot be undone.
                    </DialogDescription>
                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary">Cancel</Button>
                        </DialogClose>
                        {deletingInvitation && (
                            <Link
                                href={
                                    destroy(deletingInvitation.id).url
                                }
                                method="delete"
                                as="button"
                                preserveScroll
                                onSuccess={() => setDeletingInvitation(null)}
                                className="inline-flex items-center justify-center rounded-md bg-destructive px-4 py-2 text-sm font-medium text-white shadow-xs hover:bg-destructive/90"
                            >
                                Delete
                            </Link>
                        )}
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

AdminInvitations.layout = {
    breadcrumbs: [
        {
            title: 'Invitations',
            href: InvitationController.index.url(),
        },
    ],
};
