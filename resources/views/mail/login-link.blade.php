<!DOCTYPE html>
<html lang="pl">
    <body style="margin: 0; padding: 32px 16px; background: #faf6ee; font-family: Arial, Helvetica, sans-serif; color: #17392c;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width: 480px; margin: 0 auto;">
            <tr>
                <td style="padding: 0 0 20px; font-family: Georgia, 'Times New Roman', serif; font-size: 26px; color: #17392c;">
                    <img src="{{ url('/icon-512.png') }}" width="32" height="32" alt="" style="vertical-align: -6px; margin-right: 6px;">
                    tobym
                </td>
            </tr>
            <tr>
                <td style="padding: 32px; background: #ffffff; border: 1px solid #d9cfba; border-radius: 14px;">
                    <h1 style="margin: 0 0 14px; font-family: Georgia, 'Times New Roman', serif; font-size: 26px; font-weight: normal; color: #17392c;">
                        Twoja lista prezentów
                    </h1>
                    <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.55; color: #3d5246;">
                        Kliknij przycisk, aby zapisać listę i otwierać ją z każdego urządzenia. Hasła nie potrzebujesz.
                    </p>
                    <p style="margin: 0 0 24px;">
                        <a href="{{ $url }}" style="display: inline-block; padding: 14px 26px; background: #b5302a; color: #ffffff; font-size: 16px; font-weight: bold; text-decoration: none; border-radius: 10px;">Otwórz moją listę</a>
                    </p>
                    <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #55695e;">
                        Link działa przez {{ $minutes }} minut i tylko raz. Jeśli to nie Ty prosiłeś o ten link, zignoruj tę wiadomość.
                    </p>
                </td>
            </tr>
            <tr>
                <td style="padding: 18px 4px 0; font-size: 12px; color: #55695e;">
                    tobym · listy prezentów, z których naprawdę da się kupić
                </td>
            </tr>
        </table>
    </body>
</html>
