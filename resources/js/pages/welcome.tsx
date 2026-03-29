import { Head, Link, usePage } from '@inertiajs/react';
import { dashboard, login, register } from '@/routes';

export default function Welcome({
    canRegister = true,
}: {
    canRegister?: boolean;
}) {
    const { auth } = usePage().props;

    return (
        <>
            <Head title="Welcome">
                <link rel="preconnect" href="https://fonts.bunny.net" />
                <link
                    href="https://fonts.bunny.net/css?family=instrument-sans:400,500,600"
                    rel="stylesheet"
                />
            </Head>
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a]">
                <header className="mb-6 w-full max-w-[335px] text-sm not-has-[nav]:hidden lg:max-w-4xl">
                    <nav className="flex items-center justify-end gap-4">
                        {auth.user ? (
                            <Link
                                href={dashboard()}
                                className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                            >
                                Dashboard
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className="inline-block rounded-sm border border-transparent px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#19140035] dark:text-[#EDEDEC] dark:hover:border-[#3E3E3A]"
                                >
                                    Log in
                                </Link>
                                {canRegister && (
                                    <Link
                                        href={register()}
                                        className="inline-block rounded-sm border border-[#19140035] px-5 py-1.5 text-sm leading-normal text-[#1b1b18] hover:border-[#1915014a] dark:border-[#3E3E3A] dark:text-[#EDEDEC] dark:hover:border-[#62605b]"
                                    >
                                        Register
                                    </Link>
                                )}
                            </>
                        )}
                    </nav>
                </header>
                <div className="flex w-full items-center justify-center opacity-100 transition-opacity duration-750 lg:grow starting:opacity-0">
                    <main className="flex w-full max-w-[335px] flex-col-reverse lg:max-w-4xl lg:flex-row">
                        <div className="flex-1 rounded-br-lg rounded-bl-lg bg-white p-6 pb-12 text-[13px] leading-[20px] shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-tl-lg lg:rounded-br-none lg:p-20 dark:bg-[#161615] dark:text-[#EDEDEC] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                            <h1 className="mb-1 text-lg font-medium">
                                Epimethean Challenge
                            </h1>
                            <p className="mb-4 text-[#706f6c] dark:text-[#A1A09A]">
                                A 4X turn-based strategy game where players explore, expand, exploit, and exterminate in a battle for control of the cluster.
                            </p>
                            <p className="mb-6 text-[#706f6c] dark:text-[#A1A09A]">
                                Inspired by the original 1978 Empyrean Challenge rulebook, EC brings the classic play-by-mail space strategy experience to the browser. Build your empire, command your fleets, and outmaneuver your rivals across the stars.
                            </p>
                            <ul className="flex gap-3 text-sm leading-normal">
                                <li>
                                    <a
                                        href="https://docs.damned.dev"
                                        target="_blank"
                                        className="inline-flex items-center gap-1 rounded-sm border border-black bg-[#1b1b18] px-5 py-1.5 text-sm leading-normal text-white hover:border-black hover:bg-black dark:border-[#eeeeec] dark:bg-[#eeeeec] dark:text-[#1C1C1A] dark:hover:border-white dark:hover:bg-white"
                                    >
                                        <span>Documentation</span>
                                        <svg
                                            width={10}
                                            height={11}
                                            viewBox="0 0 10 11"
                                            fill="none"
                                            xmlns="http://www.w3.org/2000/svg"
                                            className="h-2.5 w-2.5"
                                        >
                                            <path
                                                d="M7.70833 6.95834V2.79167H3.54167M2.5 8L7.5 3.00001"
                                                stroke="currentColor"
                                                strokeLinecap="square"
                                            />
                                        </svg>
                                    </a>
                                </li>
                            </ul>
                        </div>
                        <div className="relative -mb-px flex aspect-[335/364] w-full shrink-0 items-center justify-center overflow-hidden rounded-t-lg bg-[#f5f5f4] lg:mb-0 lg:-ml-px lg:aspect-auto lg:w-[438px] lg:rounded-t-none lg:rounded-r-lg dark:bg-[#161615]">
                            <svg
                                className="w-48 opacity-100 transition-all duration-750 lg:w-56 starting:opacity-0 motion-safe:starting:scale-90"
                                viewBox="0 0 180 180"
                                fill="none"
                                xmlns="http://www.w3.org/2000/svg"
                            >
                                <path
                                    fillRule="evenodd"
                                    clipRule="evenodd"
                                    d="m 105.50024,22.224647 c -9.59169,-5.537563 -21.40871,-5.537563 -31.000093,0 L 39.054693,42.689119 C 29.463353,48.226675 23.55484,58.460531 23.55484,69.535642 v 40.928918 c 0,11.07542 5.908513,21.3092 15.499853,26.84652 l 35.445453,20.46446 c 9.591313,5.53732 21.408404,5.53732 31.000094,0 l 35.44507,-20.46446 c 9.59131,-5.53732 15.49985,-15.7711 15.49985,-26.84652 V 69.535642 c 0,-11.075111 -5.90854,-21.308967 -15.49985,-26.846523 z M 34.112797,85.737639 c -1.384445,2.397827 -1.384445,5.352099 0,7.749927 l 24.781554,42.922974 c 1.38437,2.39783 3.942853,3.87496 6.711592,3.87496 h 49.563107 c 2.76905,0 5.3273,-1.47713 6.71144,-3.87496 l 24.78194,-42.922974 c 1.38414,-2.397828 1.38414,-5.3521 0,-7.749927 L 121.88049,42.814746 c -1.38414,-2.397828 -3.94239,-3.874964 -6.71144,-3.874964 H 65.605944 c -2.768739,0 -5.327223,1.477059 -6.711592,3.874964 z"
                                    className="fill-[#1E1E1E] dark:fill-[#EDEDEC]"
                                />
                            </svg>
                            <div className="absolute inset-0 rounded-t-lg shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] lg:rounded-t-none lg:rounded-r-lg dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]"></div>
                        </div>
                    </main>
                </div>
                <div className="hidden h-14.5 lg:block"></div>
            </div>
        </>
    );
}
