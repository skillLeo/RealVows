{{-- One clear button. Table-wrapped so Outlook renders the background.

     @param string $url
     @param string $label
--}}
<table role="presentation" cellpadding="0" cellspacing="0" border="0" style="margin:8px 0 0;">
    <tr>
        <td style="background:#6B1834;border:1px solid #6B1834;border-radius:8px;">
            <a href="{{ $url }}"
               style="display:inline-block;min-height:24px;padding:16px 32px;font-family:'Inter',-apple-system,'Segoe UI',Helvetica,Arial,sans-serif;font-size:16px;font-weight:500;line-height:24px;color:#F2EFE9;text-decoration:none;">
                {{ $label }}
            </a>
        </td>
    </tr>
</table>
