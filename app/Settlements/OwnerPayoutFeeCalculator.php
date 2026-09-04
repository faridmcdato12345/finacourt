<?php

namespace App\Settlements;

use App\Enums\OwnerPayoutType;

class OwnerPayoutFeeCalculator
{
    /** @return array{fee_cents: int, net_cents: int, fee_payer: string} */
    public function quote(OwnerPayoutType $type, int $grossCents): array
    {
        $transferFee = max(0, (int) config('settlements.transfer_fee_centavos', 0));
        $feePayer = $type === OwnerPayoutType::Early
            ? (string) config('settlements.early.fee_payer', 'owner')
            : 'platform';
        $netCents = $feePayer === 'owner' ? $grossCents - $transferFee : $grossCents;

        return [
            'fee_cents' => $transferFee,
            'net_cents' => max(0, $netCents),
            'fee_payer' => $feePayer,
        ];
    }
}
