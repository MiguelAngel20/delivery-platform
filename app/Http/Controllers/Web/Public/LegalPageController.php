<?php

namespace App\Http\Controllers\Web\Public;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LegalPageController extends Controller
{
    public function terms(): Response
    {
        return $this->page('terminos');
    }

    public function privacy(): Response
    {
        return $this->page('aviso-de-privacidad');
    }

    public function feedback(): Response
    {
        return $this->page('quejas-y-sugerencias');
    }

    public function affiliation(): Response
    {
        return $this->page('afiliacion');
    }

    private function page(string $page): Response
    {
        $pages = $this->pages();

        if (! array_key_exists($page, $pages)) {
            throw new NotFoundHttpException;
        }

        $content = $pages[$page];

        return Inertia::render('public/legal/show', [
            'title' => $content['title'],
            'summary' => $content['summary'],
            'sections' => $content['sections'],
        ]);
    }

    /**
     * @return array<string, array{title: string, summary: string, sections: list<array{heading: string, body: string}>}>
     */
    private function pages(): array
    {
        return [
            'terminos' => [
                'title' => 'Términos y condiciones',
                'summary' => 'Condiciones de uso de la plataforma RIDE para pedidos y entregas.',
                'sections' => [
                    [
                        'heading' => 'Uso del servicio',
                        'body' => "Al usar RIDE aceptas utilizar la plataforma de forma responsable para solicitar pedidos, dar seguimiento a entregas y gestionar tu cuenta.\n\nNos reservamos el derecho de actualizar estos términos para mejorar el servicio o cumplir con la normativa aplicable.",
                    ],
                    [
                        'heading' => 'Pedidos y pagos',
                        'body' => "Los precios, disponibilidad y tiempos de entrega pueden variar según el negocio, la ubicación y la demanda.\n\nAl confirmar un pedido, aceptas los cargos mostrados en el resumen de compra.",
                    ],
                    [
                        'heading' => 'Cuentas de usuario',
                        'body' => 'Eres responsable de mantener la confidencialidad de tu cuenta y de la información que compartes en la plataforma.',
                    ],
                ],
            ],
            'aviso-de-privacidad' => [
                'title' => 'Aviso de privacidad',
                'summary' => 'Cómo tratamos tus datos personales al usar RIDE.',
                'sections' => [
                    [
                        'heading' => 'Datos que recopilamos',
                        'body' => 'Podemos tratar datos de identificación, contacto, dirección de entrega, historial de pedidos y preferencias necesarias para operar el servicio.',
                    ],
                    [
                        'heading' => 'Finalidad',
                        'body' => 'Usamos tu información para procesar pedidos, mejorar la experiencia, brindar soporte y cumplir obligaciones legales.',
                    ],
                    [
                        'heading' => 'Tus derechos',
                        'body' => 'Puedes solicitar acceso, corrección o eliminación de tus datos conforme a la legislación aplicable contactando a nuestro equipo de soporte.',
                    ],
                ],
            ],
            'quejas-y-sugerencias' => [
                'title' => 'Quejas y sugerencias',
                'summary' => 'Queremos mejorar. Cuéntanos tu experiencia con RIDE.',
                'sections' => [
                    [
                        'heading' => 'Cómo contactarnos',
                        'body' => "Envía tu queja o sugerencia a soporte@ride.local o desde tu perfil cuando hayas iniciado sesión.\n\nIncluye el número de pedido, fecha y una descripción clara para ayudarnos a resolver más rápido.",
                    ],
                    [
                        'heading' => 'Tiempos de respuesta',
                        'body' => 'Nuestro equipo revisará tu mensaje y te responderá a la brevedad posible en días hábiles.',
                    ],
                ],
            ],
            'afiliacion' => [
                'title' => 'Contacto para afiliación',
                'summary' => 'Únete a RIDE como negocio afiliado y llega a más clientes en Comitán.',
                'sections' => [
                    [
                        'heading' => 'Beneficios de afiliarte',
                        'body' => "Publica tu menú, recibe pedidos y gestiona tu operación desde el panel de negocio.\n\nLas empresas afiliadas tienen mayor visibilidad en la tienda.",
                    ],
                    [
                        'heading' => 'Contacto',
                        'body' => "Escríbenos a afiliaciones@ride.local o llama al +52 963 000 0000.\n\nComparte el nombre de tu negocio, giro, ubicación y una persona de contacto.",
                    ],
                ],
            ],
        ];
    }
}
