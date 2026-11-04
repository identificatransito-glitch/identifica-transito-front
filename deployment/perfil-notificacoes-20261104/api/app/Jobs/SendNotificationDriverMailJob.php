<?php
namespace App\Jobs;
use App\Enums\NotificationDriverEnum;
use App\Mail\NotificationDriverMail;
use App\Models\{NotificationDriver,Vehicle};
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\{ShouldQueue,ShouldBeUnique};
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\{InteractsWithQueue,SerializesModels};
use Illuminate\Support\Facades\{DB,Mail};
class SendNotificationDriverMailJob implements ShouldQueue, ShouldBeUnique {
    use Dispatchable,InteractsWithQueue,Queueable,SerializesModels;
    public int $tries=3;
    public int $uniqueFor=3600;
    public array $backoff=[60,300,900];
    public function __construct(private readonly int $notificationId) {}
    public function uniqueId(): string { return 'driver-alert:'.$this->notificationId; }
    public function handle(): void {
        DB::transaction(function () {
            $notice=NotificationDriver::query()->with(['user','driver'])->whereKey($this->notificationId)->lockForUpdate()->first();
            if (!$notice || $notice->mail_sent_at || !in_array((int)$notice->type,NotificationDriverEnum::values(),true) || !$notice->driver || !filter_var($notice->driver->email,FILTER_VALIDATE_EMAIL)) return;
            $vehicles=Vehicle::query()->whereRaw("REPLACE(REPLACE(UPPER(plate), '-', ''), ' ', '') = ?",[$notice->plate])->limit(2)->get();
            // Do not deliver an old alert after deletion, transfer, or ambiguous registration.
            if ($vehicles->count()!==1 || (int)$vehicles->first()->user_id!==(int)$notice->driver_id) return;
            Mail::to($notice->driver->email)->send(new NotificationDriverMail(notification:$notice,recipient:$notice->driver,isAdminCopy:false));
            DB::table('notifications_drivers')->where('id',$notice->id)->update(['mail_sent_at'=>now()]);
        });
    }
}
