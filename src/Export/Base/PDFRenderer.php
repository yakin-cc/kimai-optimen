<?php

/*
 * This file is part of the Kimai time-tracking app.
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace App\Export\Base;

use App\Export\ExportContext;
use App\Export\ExportFilename;
use App\Export\ExportItemInterface;
use App\Project\ProjectStatisticService;
use App\Repository\Query\TimesheetQuery;
use App\Utils\FileHelper;
use App\Utils\HtmlToPdfConverter;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class PDFRenderer implements DispositionInlineInterface
{
    use RendererTrait;
    use DispositionInlineTrait;

    /**
     * @var Environment
     */
    private $twig;
    /**
     * @var HtmlToPdfConverter
     */
    private $converter;
    /**
     * @var ProjectStatisticService
     */
    private $projectStatisticService;
    /**
     * @var string
     */
    private $id = 'pdf';
    /**
     * @var string
     */
    private $template = 'default.pdf.twig';
    /**
     * @var array
     */
    private $pdfOptions = [];

    public function __construct(Environment $twig, HtmlToPdfConverter $converter, ProjectStatisticService $projectStatisticService)
    {
        $this->twig = $twig;
        $this->converter = $converter;
        $this->projectStatisticService = $projectStatisticService;
    }

    protected function getTemplate(): string
    {
        return '@export/' . $this->template;
    }

    protected function getOptions(TimesheetQuery $query): array
    {
        $decimal = false;
        if (null !== $query->getCurrentUser()) {
            $decimal = $query->getCurrentUser()->isExportDecimal();
        } elseif (null !== $query->getUser()) {
            $decimal = $query->getUser()->isExportDecimal();
        }

        return ['decimal' => $decimal];
    }

    public function getPdfOptions(): array
    {
        return $this->pdfOptions;
    }

    public function setPdfOption(string $key, string $value): PDFRenderer
    {
        $this->pdfOptions[$key] = $value;

        return $this;
    }

    /**
     * @param ExportItemInterface[] $timesheets
     * @param TimesheetQuery $query
     * @return Response
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function render(array $timesheets, TimesheetQuery $query): Response
    {
        $filename = new ExportFilename($query);
        $context = new ExportContext();
        $context->setOption('filename', $filename->getFilename());

        $summary = $this->calculateSummary($timesheets);
        $content = $this->twig->render($this->getTemplate(), array_merge([
            'entries' => $timesheets,
            'query' => $query,
            // @deprecated since 1.13
            'now' => new \DateTime('now', new \DateTimeZone(date_default_timezone_get())),
            'summaries' => $summary,
            'budgets' => $this->calculateProjectBudget($timesheets, $query, $this->projectStatisticService),
            'decimal' => false,
            'pdfContext' => $context
        ], $this->getOptions($query)));

        $pdfOptions = array_merge($context->getOptions(), $this->getPdfOptions());

        $content = $this->converter->convertToPdf($content, $pdfOptions);

        $response = new Response($content);

        $filename = $context->getOption('filename');
        if (empty($filename)) {
            $filename = new ExportFilename($query);
            $filename = $filename->getFilename();
        }

        $filename = FileHelper::convertToAsciiFilename($filename);

        $disposition = $response->headers->makeDisposition($this->getDisposition(), $filename . '.pdf');

        $response->headers->set('Content-Type', 'application/pdf');
        $response->headers->set('Content-Disposition', $disposition);

        return $response;
    }

    public function setTemplate(string $filename): PDFRenderer
    {
        $this->template = $filename;

        return $this;
    }

    public function setId(string $id): PDFRenderer
    {
        $this->id = $id;

        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }
}
