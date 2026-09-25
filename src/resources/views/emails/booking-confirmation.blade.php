<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Booking Confirmation</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background-color: #f4f4f7; padding: 20px; }
        .card { max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 30px; border: 1px solid #e2e8f0; }
        .header { font-size: 20px; font-weight: bold; color: #2d3748; margin-bottom: 20px; }
        .badge { display: inline-block; background-color: #def7ec; color: #03543f; font-weight: 600; padding: 4px 12px; border-radius: 9999px; font-size: 14px; }
        .details { margin: 20px 0; padding: 15px; background: #f8fafc; border-radius: 6px; }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">Booking Confirmed! <span class="badge">Active</span></div>
        <p>Hello <strong>{{ $customerName }}</strong>,</p>
        <p>Your appointment has been successfully scheduled with <strong>{{ $tenantName }}</strong>.</p>
        
        <div class="details">
            <p><strong>Service:</strong> {{ $serviceName }}</p>
            <p><strong>Date & Time:</strong> {{ $appointmentTime }}</p>
        </div>

        <p>If you need to reschedule or cancel, please reach out directly through our booking portal.</p>
        <p>Best regards,<br>{{ $tenantName }} Team</p>
    </div>
</body>
</html>
