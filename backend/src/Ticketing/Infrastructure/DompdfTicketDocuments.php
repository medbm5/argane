<?php

declare(strict_types=1);

namespace App\Ticketing\Infrastructure;

use App\Booking\Domain\Model\Order;
use App\Shared\Domain\VenueTime;
use App\Ticketing\Application\QrSigner;
use App\Ticketing\Application\TicketDocuments;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\Output\QRMarkupSVG;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use DateTimeImmutable;
use Dompdf\Dompdf;
use Dompdf\Options;
use Twig\Environment;

/**
 * Renders tickets with Twig + Dompdf (pure PHP: no GD needed, the QR code is SVG).
 * DejaVu Sans carries the accents; layout echoes the site (ink bar, pop colours).
 */
final readonly class DompdfTicketDocuments implements TicketDocuments
{
    public function __construct(
        private Environment $twig,
        private QrSigner $signer,
    ) {
    }

    public function pdf(Order $order, array $tickets): string
    {
        $qr = new QRCode(new QROptions([
            'outputInterface' => QRMarkupSVG::class,
            'outputBase64' => true,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'svgUseFillAttributes' => true,
            'drawLightModules' => false,
        ]));

        $performance = $order->performance();
        $startsAt = VenueTime::toVenue($performance->startsAt());
        $doorsAt = VenueTime::toVenue($performance->doorsAt());
        $pages = [];
        foreach ($tickets as $ticket) {
            $pages[] = [
                'ticket' => $ticket,
                'qr' => $qr->render($this->signer->sign($ticket)),
                'code' => strtoupper(substr($ticket->id()->toBase58(), -8)),
            ];
        }

        $html = $this->twig->render('ticket/pdf.html.twig', [
            'order' => $order,
            'event' => $performance->event(),
            'venue' => $performance->event()->venue(),
            'date' => self::frenchDate($startsAt),
            'time' => $startsAt->format('H:i'),
            'doors' => $doorsAt->format('H:i'),
            'pages' => $pages,
        ]);

        $options = new Options();
        $options->setDefaultFont('DejaVu Sans');
        $options->setIsRemoteEnabled(false);
        $options->setDefaultPaperSize('a4');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        return (string) $dompdf->output();
    }

    /** "vendredi 12 décembre 2026" without ext-intl. */
    private static function frenchDate(DateTimeImmutable $at): string
    {
        $days = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
        $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];

        return sprintf('%s %d %s %s', $days[(int) $at->format('w')], (int) $at->format('j'), $months[(int) $at->format('n') - 1], $at->format('Y'));
    }
}
