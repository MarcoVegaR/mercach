import { cn } from '@/lib/utils';
import { useEffect, useState } from 'react';

interface Section {
    id: string;
    title: string;
}

interface SectionNavProps {
    sections: Section[];
    className?: string;
    activeTab?: string;
}

export function SectionNav({ sections, className, activeTab }: SectionNavProps) {
    const [activeSection, setActiveSection] = useState<string>(sections[0]?.id || '');

    useEffect(() => {
        // Delay to ensure tab content is rendered
        const timeoutId = setTimeout(() => {
            // Intersection Observer for scroll-spy
            const observer = new IntersectionObserver(
                (entries) => {
                    const visibleEntries = entries.filter((e) => e.isIntersecting);
                    if (visibleEntries.length > 0) {
                        // Use the first visible entry
                        setActiveSection(visibleEntries[0].target.id);
                    }
                },
                {
                    rootMargin: '-20% 0px -70% 0px',
                    threshold: 0,
                },
            );

            // Observe all sections
            sections.forEach(({ id }) => {
                const element = document.getElementById(id);
                if (element) {
                    observer.observe(element);
                }
            });

            return () => observer.disconnect();
        }, 100);

        return () => clearTimeout(timeoutId);
    }, [sections, activeTab]);

    const scrollToSection = (id: string) => {
        const element = document.getElementById(id);
        if (element) {
            element.scrollIntoView({ behavior: 'smooth', block: 'start' });
            // Set focus for accessibility
            element.setAttribute('tabindex', '-1');
            element.focus();
        }
    };

    return (
        <nav className={cn('sticky top-6', className)} aria-label="Secciones de la página">
            <ul className="space-y-2">
                {sections.map(({ id, title }) => (
                    <li key={id}>
                        <button
                            onClick={() => scrollToSection(id)}
                            className={cn(
                                'w-full rounded-md px-3 py-1.5 text-left text-sm transition-colors',
                                'hover:bg-muted focus:bg-muted focus:ring-ring focus:ring-2 focus:outline-none',
                                activeSection === id ? 'bg-muted text-foreground font-medium' : 'text-muted-foreground',
                            )}
                            aria-current={activeSection === id ? 'location' : undefined}
                        >
                            {title}
                        </button>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
