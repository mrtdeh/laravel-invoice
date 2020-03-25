<?php


namespace NeptuneSoftware\Invoicable\Services;

use Dompdf\Dompdf;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\View;
use NeptuneSoftware\Invoicable\Models\Bill;
use NeptuneSoftware\Invoicable\MoneyFormatter;
use NeptuneSoftware\Invoicable\Scopes\InvoiceScope;
use NeptuneSoftware\Invoicable\Interfaces\BillServiceInterface;
use Symfony\Component\HttpFoundation\Response;

class BillService implements BillServiceInterface
{
    /**
     * @var Bill $billModel
     */
    private $billModel;

    /**
     * @inheritDoc
     */
    public function create(Model $model, ?array $bill = []): BillServiceInterface
    {
        $this->billModel = $model->bills()->create($bill);

        return $this;
    }

    /**
     * @inheritDoc
     */
    public function getBill(): Bill
    {
        return $this->billModel;
    }

    /**
     * @inheritDoc
     */
    public function addAmountExclTax(Model $model, int $amount, string $description, float $taxPercentage = 0): Bill
    {
        $tax = $amount * $taxPercentage;

        $this->billModel->lines()->create([
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
    public function addAmountInclTax(Model $model, int $amount, string $description, float $taxPercentage = 0): Bill
    {
        $this->billModel->lines()->create([
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
    public function recalculate(): Bill
    {
        $this->billModel->total = $this->billModel->lines()->sum('amount');
        $this->billModel->tax = $this->billModel->lines()->sum('tax');
        $this->billModel->save();
        return $this->billModel;
    }

    /**
     * @inheritDoc
     */
    public function view(array $data = []): \Illuminate\Contracts\View\View
    {
        return View::make('invoicable::receipt', array_merge($data, [
            'invoice' => $this->billModel,
            'moneyFormatter' => new MoneyFormatter(
                $this->billModel->currency,
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
        $filename = $this->billModel->reference . '.pdf';

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
    public function findByReference(string $reference): ?Bill
    {
        return Bill::where('reference', $reference)->withoutGlobalScope(InvoiceScope::class)->first();
    }

    /**
     * @inheritDoc
     */
    public function findByReferenceOrFail(string $reference): Bill
    {
        return Bill::where('reference', $reference)->withoutGlobalScope(InvoiceScope::class)->firstOrFail();
    }
}
