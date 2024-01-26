<?php

namespace alextrellez\QueueMonitor\Services;

use App\Http\Controllers\EmailController;
use Illuminate\Contracts\Queue\Job as JobContract;
use Illuminate\Queue\Events\JobExceptionOccurred;
use Illuminate\Queue\Events\JobFailed;
use Illuminate\Queue\Events\JobProcessed;
use Illuminate\Queue\Events\JobProcessing;
use Illuminate\Support\Carbon;
use alextrellez\QueueMonitor\Models\Contracts\MonitorContract;
use alextrellez\QueueMonitor\Traits\IsMonitored;
use Illuminate\Http\Request;
use Throwable;

class QueueMonitor
{
    private const TIMESTAMP_EXACT_FORMAT = 'Y-m-d H:i:s.u';

    public const MAX_BYTES_TEXT = 65535;

    public const MAX_BYTES_LONGTEXT = 4294967295;

    public static $loadMigrations = false;

    public static $model;

    /**
     * Get the model used to store the monitoring data.
     *
     * @return \alextrellez\QueueMonitor\Models\Contracts\MonitorContract
     */
    public static function getModel(): MonitorContract
    {
        return new self::$model();
    }

    /**
     * Handle Job Processing.
     *
     * @param JobProcessing $event
     *
     * @return void
     */
    public static function handleJobProcessing(JobProcessing $event): void
    {
        self::jobStarted($event->job);
    }

    /**
     * Handle Job Processed.
     *
     * @param JobProcessed $event
     *
     * @return void
     */
    public static function handleJobProcessed(JobProcessed $event): void
    {
        self::jobFinished($event->job);
    }

    /**
     * Handle Job Failing.
     *
     * @param JobFailed $event
     *
     * @return void
     */
    public static function handleJobFailed(JobFailed $event): void
    {
        self::jobFinished($event->job, true, $event->exception);
    }

    /**
     * Handle Job Exception Occurred.
     *
     * @param JobExceptionOccurred $event
     *
     * @return void
     */
    public static function handleJobExceptionOccurred(JobExceptionOccurred $event): void
    {
        self::jobFinished($event->job, true, $event->exception);
    }

    /**
     * Get Job ID.
     *
     * @param JobContract $job
     *
     * @return string|int
     */
    public static function getJobId(JobContract $job)
    {
        if ($jobId = $job->getJobId()) {
            return $jobId;
        }

        return sha1($job->getRawBody());
    }

    /**
     * Start Queue Monitoring for Job.
     *
     * @param JobContract $job
     *
     * @return void
     */
    protected static function jobStarted(JobContract $job): void
    {
        if ( ! self::shouldBeMonitored($job)) {
            return;
        }

        $now = Carbon::now();
        
        $model = self::getModel();

        //Se obtiene el payload(podcast) para guardarlo y poder reejecutar
        $data = $job->payload();
        $data = $data['data']['command'];
        $data = unserialize($data);

        $nombreJob = explode('\\', $job->resolveName())[2];

        switch ($nombreJob) {
            case 'NovedadesJob':
            case 'ActualizacionClienteJob':
            case 'BaseGarantizadoJob':
                $payload = json_encode($data->request->query->all());
                break;
            case 'GarantiaJob':
            case 'GestorInmaterialJob':
            case 'CarteraJob':
            case 'RenovacionesJob':
                $payload = $data->id;
                break;
            case 'InmaterialJob':
                $payload = $data->sigPagoId;
                break;
            case 'AbonoMigracionJob':
                $payload =  $data->tipo.','.$data->abono;
                break;
            case 'CondonacionDistribucionJob':
                $payload =  $data->data.','.$data->user;
                break;
            case 'CrearRegistroGarantiaJob':
            case 'CrearRegistroReclamacionRiesgoNoGarantizadosJob':
            case 'CrearRegistroReclamacionRiesgoOperacionalJob':
            case 'CrearRegistrosCarteraJob':
            case 'EliminacionAbonosJob':
                if (is_array($data->registro)){
                    $data->registro = json_encode($data->registro);
                }
                $payload =  $data->registro;
                break;
            case 'GarantiaDigitalSig3Job':
                $payload =  $data->nit.','.$data->convenio.','.$data->user.','.$data->password.','.$data->contenido;
                break;                
            case 'GarantiaDigitalSig2Job':
                $payload =  $data->nit.','.$data->convenio.','.$data->contenido;
                break;
                
            case 'InsertDataCarguesJob':
                $payload =  $data->cargue.','.$data->data;
                break;                
            case 'MigrarPagoObligacionJob':
                if (is_array($data->archivo)){
                    $data->archivo = json_encode($data->archivo);
                }
                $payload =  $data->archivo;
                break;         
            default:
                $payload = "Payload not found";

                $datos = new Request([
                    'asunto' => 'Job no inicializado',
                    'remitente' => env('MAIL_USERNAME'),
                    //'destinatario' => [$log->sigUsuario->email],
                    'destinatario' => 'jtrellez@fga.com.co',
                    'copia_oculta' => ["sistemas@fga.com.co"],
                    'nombre_remitente' => "FGA Fondo de Garantías",
                    'contenido' => '<tbody><center><h4>'.$nombreJob.' no ejecutado </h4></center><tr><td>No se identificó el payload del job <br><br>'.json_encode($data).' <br><br> por favor no responda este mensaje.</td></tr></tbody>',
                ]);

                $send_mail = EmailController::enviarEmail($datos);
                if ($send_mail->status() != 200) {
                    throw new \Exception($send_mail->getData()->message);
                }
                break;
        }

        $model::query()->create([
            'job_id' => self::getJobId($job),
            'name' => $job->resolveName(),
            'queue' => $job->getQueue(),
            'started_at' => $now,
            'started_at_exact' => $now->format(self::TIMESTAMP_EXACT_FORMAT),
            'attempt' => $job->attempts(),
            'data' => $payload
        ]);
    }

    /**
     * Finish Queue Monitoring for Job.
     *
     * @param JobContract $job
     * @param bool $failed
     * @param Throwable|null $exception
     *
     * @return void
     */
    protected static function jobFinished(JobContract $job, bool $failed = false, ?Throwable $exception = null): void
    {
        if ( ! self::shouldBeMonitored($job)) {
            return;
        }

        $model = self::getModel();

        $monitor = $model::query()
            ->where('job_id', self::getJobId($job))
            ->where('attempt', $job->attempts())
            ->orderByDesc('started_at')
            ->first();

        if (null === $monitor) {
            return;
        }

        /** @var MonitorContract $monitor */
        $now = Carbon::now();

        if ($startedAt = $monitor->getStartedAtExact()) {
            $timeElapsed = (float) $startedAt->diffInSeconds($now) + $startedAt->diff($now)->f;
        }

        /** @var IsMonitored $resolvedJob */
        $resolvedJob = $job->resolveName();

        if (null === $exception && false === $resolvedJob::keepMonitorOnSuccess()) {
            $monitor->delete();

            return;
        }

        $attributes = [
            'finished_at' => $now,
            'finished_at_exact' => $now->format(self::TIMESTAMP_EXACT_FORMAT),
            'time_elapsed' => $timeElapsed ?? 0.0,
            'failed' => $failed,
        ];

        if (null !== $exception) {
            $attributes += [
                'exception' => mb_strcut((string) $exception, 0, min(PHP_INT_MAX, self::MAX_BYTES_LONGTEXT)),
                'exception_class' => get_class($exception),
                'exception_message' => mb_strcut($exception->getMessage(), 0, self::MAX_BYTES_TEXT),
            ];
        }

        $monitor->update($attributes);
    }

    /**
     * Determine weather the Job should be monitored, default true.
     *
     * @param JobContract $job
     *
     * @return bool
     */
    public static function shouldBeMonitored(JobContract $job): bool
    {
        return array_key_exists(IsMonitored::class, ClassUses::classUsesRecursive(
            $job->resolveName()
        ));
    }
}
