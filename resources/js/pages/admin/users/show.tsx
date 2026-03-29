import { Head } from '@inertiajs/react';
import { index, show } from '@/actions/App/Http/Controllers/Admin/UserController';
import Heading from '@/components/heading';
import { Badge } from '@/components/ui/badge';
import type { User } from '@/types';

export default function UserShow({ user }: { user: User }) {
    return (
        <>
            <Head title={user.name} />

            <div className="px-4 py-6">
                <Heading title={user.name} description="User details" />

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
            </div>
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
