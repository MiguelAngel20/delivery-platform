import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg
            {...props}
            viewBox="0 0 32 32"
            fill="none"
            xmlns="http://www.w3.org/2000/svg"
            aria-hidden="true"
        >
            <rect width="32" height="32" rx="8" fill="var(--navy)" />
            <path
                d="M9 16h9.5M15 11.5 22.5 16 15 20.5"
                stroke="var(--primary)"
                strokeWidth="2.4"
                strokeLinecap="round"
                strokeLinejoin="round"
            />
        </svg>
    );
}
