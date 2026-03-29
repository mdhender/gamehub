import { Head, Link } from '@inertiajs/react';
import UserController, { show } from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import type { User } from '@/types';

export default function AdminUsers({ users }: { users: User[] }) {
    return (
        <>
            <Head title="Users" />

            <div className="px-4 py-6">
                <Heading title="Users" description="All registered users" />

                <div className="overflow-hidden rounded-lg border border-sidebar-border/70 dark:border-sidebar-border">
                    <table className="w-full text-sm">
                        <thead className="bg-muted/50 text-muted-foreground">
                            <tr>
                                <th className="px-4 py-3 text-left font-medium">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Email
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Role
                                </th>
                                <th className="px-4 py-3 text-left font-medium">
                                    Joined
                                </th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-sidebar-border/70 dark:divide-sidebar-border">
                            {users.map((user) => (
                                <tr key={user.id} className="hover:bg-muted/30">
                                    <td className="px-4 py-3">
                                        <Link
                                            href={show.url(user)}
                                            className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current dark:decoration-neutral-500"
                                        >
                                            {user.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {user.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        {user.is_admin ? (
                                            <Badge variant="default">
                                                Admin
                                            </Badge>
                                        ) : (
                                            <Badge variant="secondary">
                                                User
                                            </Badge>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-muted-foreground">
                                        {new Date(
                                            user.created_at,
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

AdminUsers.layout = {
    breadcrumbs: [
        {
            title: 'Users',
            href: UserController.index.url(),
        },
    ],
};
