import { Form, Head } from '@inertiajs/react';
import InvitationController, {
    store,
} from '@/actions/App/Http/Controllers/Admin/InvitationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
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
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {invitations.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={4}
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
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            </div>
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
