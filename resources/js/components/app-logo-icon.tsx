import { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <svg {...props} viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false">
            <g fill="currentColor">
                {/* Top-left block */}
                <rect x="2.75" y="2.75" width="8.5" height="8.5" rx="2" />
                {/* Top-right block */}
                <rect x="12.75" y="2.75" width="8.5" height="8.5" rx="2" />
                {/* Bottom-center block */}
                <rect x="7.75" y="12.75" width="8.5" height="8.5" rx="2" />
            </g>
        </svg>
    );
}
