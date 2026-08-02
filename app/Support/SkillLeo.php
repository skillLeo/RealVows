<?php

namespace App\Support;

/**
 * Who is actually taking the money, for the receipt.
 *
 * RealVows is the client's product; SkillLeo bills on his behalf until his
 * own merchant account clears. A receipt has to say so — a charge on a
 * statement from a name the member does not recognise is the single most
 * common cause of a chargeback.
 */
class SkillLeo
{
    public const BILLING = [
        'name' => 'SkillLeo SMC (Pvt) Ltd',
        'onBehalfOf' => 'RealVows',
        'note' => 'Charges appear on your statement as SKILLLEO.',
    ];
}
