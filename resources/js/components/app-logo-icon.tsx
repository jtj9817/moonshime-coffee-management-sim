import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 32 32" xmlns="http://www.w3.org/2000/svg">
            <rect width="32" height="32" rx="6" fill="#111827"/>
            <path d="M8 18H20V20C20 22.2091 18.2091 24 16 24H12C9.79086 24 8 22.2091 8 20V18Z" fill="#e5e7eb"/>
            <path d="M20 19H22C23.1046 19 24 19.8954 24 21C24 22.1046 23.1046 23 22 23H20" stroke="#e5e7eb" strokeWidth="2" fill="none"/>
            <polyline points="10,14 13,11 16,14 22,6" fill="none" stroke="#10b981" strokeWidth="2.5" strokeLinecap="round" strokeLinejoin="round"/>
            <path d="M22 6L18 6" stroke="#10b981" strokeWidth="1.5" strokeLinecap="round"/>
            <path d="M22 6L22 10" stroke="#10b981" strokeWidth="1.5" strokeLinecap="round"/>
        </svg>
    );
}