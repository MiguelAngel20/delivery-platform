import { Head, Link } from '@inertiajs/react';
import { PageContainer } from '@/components/layout/page';
import { home } from '@/routes';

type Props = {
    title: string;
    summary: string;
    sections: Array<{
        heading: string;
        body: string;
    }>;
};

export default function LegalShow({ title, summary, sections }: Props) {
    return (
        <>
            <Head title={title} />
            <PageContainer className="gap-6 px-4 py-6 md:px-6">
                <div className="space-y-2">
                    <Link
                        href={home()}
                        className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                    >
                        Volver al inicio
                    </Link>
                    <h1 className="text-2xl font-semibold text-navy md:text-3xl">
                        {title}
                    </h1>
                    <p className="max-w-2xl text-sm text-muted-foreground md:text-base">
                        {summary}
                    </p>
                </div>

                <div className="space-y-5">
                    {sections.map((section) => (
                        <section key={section.heading} className="space-y-2">
                            <h2 className="text-lg font-semibold text-navy">
                                {section.heading}
                            </h2>
                            <p className="max-w-3xl text-sm leading-relaxed text-muted-foreground whitespace-pre-line">
                                {section.body}
                            </p>
                        </section>
                    ))}
                </div>
            </PageContainer>
        </>
    );
}
