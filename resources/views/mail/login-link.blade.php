<!DOCTYPE html>
<html lang="pl">
    <body style="margin: 0; padding: 32px 16px; background: #f6f4ef; font-family: Arial, Helvetica, sans-serif; color: #1f1f1f;">
        <div style="max-width: 480px; margin: 0 auto; padding: 32px; background: #ffffff; border-radius: 12px;">
            <h1 style="margin: 0 0 16px; font-size: 22px;">Twoja lista prezentów</h1>
            <p style="margin: 0 0 24px; font-size: 16px; line-height: 1.5;">
                Kliknij przycisk, aby zapisać listę i wrócić do niej z dowolnego urządzenia. Hasła nie potrzebujesz.
            </p>
            <p style="margin: 0 0 24px;">
                <a href="{{ $url }}" style="display: inline-block; padding: 14px 24px; background: #17392c; color: #ffffff; font-size: 16px; font-weight: bold; text-decoration: none; border-radius: 8px;">Otwórz moją listę</a>
            </p>
            <p style="margin: 0; font-size: 13px; line-height: 1.5; color: #5c5c5c;">
                Link działa przez {{ $minutes }} minut. Jeśli to nie Ty prosiłeś o ten link, zignoruj tę wiadomość.
            </p>
        </div>
    </body>
</html>
