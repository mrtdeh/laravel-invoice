<?php


namespace NeptuneSoftware\Invoicable\Services;

use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use NeptuneSoftware\Invoicable\Models\Invoice;
use NeptuneSoftware\Invoicable\MoneyFormatter;
use NeptuneSoftware\Invoicable\Interfaces\InvoiceServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class InvoiceService implements InvoiceServiceInterface
{
    /**
     * @var Invoice $invoiceModel
     */
    private $invoiceModel;

    /**
     * @inheritDoc
     */
    public function create(Model $model, ?array $invoice = []): InvoiceServiceInterface
    {
        $this->invoiceModel = $model->invoices()->create($invoice);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getInvoice(): Invoice
    {
        return $this->invoiceModel;
    }

    /**
     * @inheritDoc
     */
    public function addAmountExclTax(Model $model, int $amount, string $description, float $taxPercentage = 0): Invoice
    {
        $tax = $amount * $taxPercentage;

        $this->invoiceModel->lines()->create([
            'amount'           => $amount + $tax,
            'description'      => $description,
            'tax'              => $tax,
            'tax_percentage'   => $taxPercentage,
            'invoiceable_id'   => $model->id,
            'invoiceable_type' => get_class($model),
        ]);
        return $this->recalculate();
    }

    /**
     * @inheritDoc
     */
    public function addAmountInclTax(Model $model, int $amount, string $description, float $taxPercentage = 0): Invoice
    {
        $this->invoiceModel->lines()->create([
            'amount'           => $amount,
            'description'      => $description,
            'tax'              => $amount - $amount / (1 + $taxPercentage),
            'tax_percentage'   => $taxPercentage,
            'invoiceable_id'   => $model->id,
            'invoiceable_type' => get_class($model),
        ]);

        return $this->recalculate();
    }

    /**
     * @inheritDoc
     */
    public function recalculate(): Invoice
    {
        $this->invoiceModel->total = $this->invoiceModel->lines()->sum('amount');
        $this->invoiceModel->tax = $this->invoiceModel->lines()->sum('tax');
        $this->invoiceModel->save();
        return $this->invoiceModel;
    }

    /**
     * @inheritDoc
     */
    public function view(array $data = []): \Illuminate\Contracts\View\View
    {
        return View::make('invoicable::receipt', array_merge($data, [
            'invoice' => $this->invoiceModel,
            'moneyFormatter' => new MoneyFormatter(
                $this->invoiceModel->currency,
                config('invoicable.locale')
            ),
        ]));
    }

    /**
     * @inheritDoc
     */
    public function pdf(array $data = []): string
    {
        if (! defined('DOMPDF_ENABLE_AUTOLOAD')) {
            define('DOMPDF_ENABLE_AUTOLOAD', false);
        }

        if (file_exists($configPath = base_path().'/vendor/dompdf/dompdf/dompdf_config.inc.php')) {
            require_once $configPath;
        }

        $dompdf = new Dompdf;
        $dompdf->loadHtml($this->view($data)->render());
        $dompdf->render();
        return $dompdf->output();
    }

    /**
     * @inheritDoc
     */
    public function download(array $data = []): Response
    {
        $filename = $this->invoiceModel->reference . '.pdf';

        return new Response($this->pdf($data), 200, [
            'Content-Description' => 'File Transfer',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
            'Content-Transfer-Encoding' => 'binary',
            'Content-Type' => 'application/pdf',
        ]);
    }

    /**
     * @inheritDoc
     */
    public function findByReference(string $reference): ?Invoice
    {
        return Invoice::where('reference', $reference)->first();
    }

    /**
     * @inheritDoc
     */
    public function findByReferenceOrFail(string $reference): Invoice
    {
        return Invoice::where('reference', $reference)->firstOrFail();
    }
}
