<!DOCTYPE html>
<html>

<head>
    <meta charset="utf-8">
    <title>Certificate of Legitimacy - {{ $legitimacy->alias }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            margin: 20mm;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            color: #000;
            position: relative;
            height: 100%;
        }

        /* Watermark Background */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            opacity: 0.05;
            z-index: -1;
            width: 500px;
            height: 500px;
        }

        /* Certificate Container */
        .certificate-container {
            border: 10px double #800000;
            padding: 20px;
            height: 100%;
            position: relative;
        }

        .inner-border {
            border: 3px solid #d4af37;
            padding: 30px 40px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }

        /* Status Badge */
        .status-badge {
            position: absolute;
            top: 35px;
            right: 50px;
            padding: 6px 12px;
            border-radius: 4px;
            font-weight: bold;
            font-size: 10px;
            text-transform: uppercase;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
            border: 2px solid #059669;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
            border: 2px solid #d97706;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
            border: 2px solid #dc2626;
        }

        /* Header with Dual Logos */
        .header-section {
            text-align: center;
            margin-bottom: 20px;
            border-bottom: 4px solid #1a5490;
            padding-bottom: 15px;
        }

        .header-content {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .logo-left,
        .logo-right {
            display: table-cell;
            width: 40%;
            vertical-align: middle;
            text-align: center;
        }

        .header-text {
            display: table-cell;
            width: 60%;
            vertical-align: middle;
            text-align: center;
        }

        .logo {
            width: 120px;
            height: 120px;
        }

        .logo-placeholder {
            width: 70px;
            height: 70px;
            border: 2px solid #1a5490;
            display: inline-block;
            border-radius: 50%;
        }

        .organization-name {
            font-size: 20px;
            font-weight: bold;
            color: #800000;
            text-transform: uppercase;
            letter-spacing: 2px;
            margin-bottom: 5px;
        }

        .organization-subtitle {
            font-size: 11px;
            color: #666;
            margin: 2px 0;
        }

        /* Certificate Title */
        .certificate-title {
            text-align: center;
            margin: 25px 0 20px 0;
        }

        .certificate-title h1 {
            font-size: 32px;
            font-weight: bold;
            color: #800000;
            text-transform: uppercase;
            letter-spacing: 4px;
            margin-bottom: 8px;
        }

        .certificate-subtitle {
            font-size: 13px;
            color: #333;
            font-style: italic;
            margin-bottom: 5px;
        }

        /* Main Content */
        .content-section {
            text-align: center;
            flex-grow: 1;
        }

        .certify-intro {
            font-size: 12px;
            margin-bottom: 15px;
            color: #333;
        }

        .recipient-name {
            font-size: 26px;
            font-weight: bold;
            color: #800000;
            margin: 15px 0;
            text-transform: uppercase;
            border-bottom: 3px solid #d4af37;
            display: inline-block;
            padding-bottom: 5px;
        }

        .alias-display {
            font-size: 18px;
            font-style: italic;
            color: #1a5490;
            margin: 10px 0 20px 0;
        }

        /* Details Table */
        .details-section {
            margin: 20px auto;
            max-width: 500px;
        }

        .detail-row {
            display: table;
            width: 100%;
            margin: 8px 0;
            font-size: 11px;
        }

        .detail-label {
            display: table-cell;
            width: 45%;
            text-align: right;
            padding-right: 15px;
            font-weight: bold;
            color: #800000;
        }

        .detail-value {
            display: table-cell;
            width: 55%;
            text-align: left;
            color: #333;
        }

        /* Statement */
        .certificate-statement {
            margin: 20px 50px;
            text-align: justify;
            font-size: 11px;
            line-height: 1.6;
            color: #333;
        }

        /* Signatories */
        .signatories-section {
            margin-top: 30px;
            display: table;
            width: 100%;
        }

        .signatory-box {
            display: table-cell;
            text-align: center;
            padding: 0 15px;
            vertical-align: top;
        }

        .signature-image {
            max-height: 45px;
            max-width: 150px;
            margin: 5px 0;
        }

        .signature-placeholder {
            height: 45px;
        }

        .signature-line {
            border-top: 2px solid #000;
            margin: 8px auto 5px auto;
            width: 180px;
        }

        .signatory-name {
            font-weight: bold;
            font-size: 11px;
            color: #000;
            text-transform: uppercase;
        }

        .signatory-role {
            font-size: 9px;
            color: #666;
            font-style: italic;
            margin-top: 2px;
        }

        .signatory-date {
            font-size: 9px;
            color: #666;
            margin-top: 3px;
        }

        /* Footer */
        .certificate-footer {
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 3px solid #800000;
            padding-top: 10px;
            margin-top: 20px;
        }

        .certificate-number {
            font-weight: bold;
            color: #1a5490;
            font-size: 10px;
        }

        .footer-line {
            margin-top: 3px;
        }
    </style>
</head>

<body>

    <!-- Watermark -->
    @if(file_exists(public_path('images/perpetuallogo.jpg')))
        <div class="watermark">
            <img src="{{ public_path('images/perpetuallogo.jpg') }}" alt="Watermark"
                style="width: 100%; height: 100%; object-fit: contain;">

                 <img src="{{ public_path('images/perpetuallogo.jpg') }}" alt="Watermark"
                style="width: 100%; height: 100%; object-fit: contain;">
        </div>
    @endif

    <div class="certificate-container">
        <div class="inner-border">
            <!-- Status Badge -->
            <div class="status-badge status-{{ $legitimacy->status }}">
                {{ strtoupper($legitimacy->status) }}
            </div>

            <!-- Header with Dual Logos -->
            <div class="header-section">
                <div class="header-content">
                    <!-- Left Logo -->
                    <div class="logo-left">
                        @if(file_exists(public_path('images/perpetuallogo.jpg')))
                            <img src="{{ public_path('images/perpetuallogo.jpg') }}" alt="Logo" class="logo">
                        @else
                            <div class="logo-placeholder"></div>
                        @endif
                    </div>

                    <!-- Center Text -->
                    <div class="header-text">
                        <div class="organization-name">Triskelion Grand Fraternity</div>
                        <div class="organization-subtitle">Grand Chapter 2 • Verum Chapter</div>
                    </div>

                    <!-- Right Logo -->
                    <div class="logo-right">
                        @if(file_exists(public_path('images/perpetuallogo.jpg')))
                            <img src="{{ public_path('images/perpetuallogo.jpg') }}" alt="Logo" class="logo">
                        @else
                            <div class="logo-placeholder"></div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Main Content -->
            <div class="content-section">
                <!-- Title -->
                <div class="certificate-title">
                    <h1>Certificate of Legitimacy</h1>
                    <div class="certificate-subtitle">This is to certify that</div>
                </div>

                <!-- Recipient Info -->
                <div class="certify-intro">
                    The individual named below is a legitimate and active member of<br>
                    the Triskelion Grand Fraternity
                </div>

                <div class="recipient-name">{{ strtoupper($user->name) }}</div>
                <div class="alias-display">"{{ $legitimacy->alias }}"</div>

                <!-- Details -->
                <div class="details-section">
                    <div class="detail-row">
                        <div class="detail-label">Chapter:</div>
                        <div class="detail-value">{{ $legitimacy->chapter }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Position:</div>
                        <div class="detail-value">{{ $legitimacy->position }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Fraternity Number:</div>
                        <div class="detail-value">{{ $legitimacy->fraternity_number }}</div>
                    </div>
                    <div class="detail-row">
                        <div class="detail-label">Certificate Date:</div>
                        <div class="detail-value">{{ $certificateDate }}</div>
                    </div>
                </div>

                <!-- Statement -->
                <div class="certificate-statement">
                    @if($legitimacy->admin_note)
                        {{ $legitimacy->admin_note }}
                    @else
                        This certificate serves as official documentation of membership standing and affirms that the bearer
                        has met all requirements and obligations as prescribed by the Triskelion Grand Fraternity. The
                        holder of this certificate is recognized as a brother/sister in good standing and is entitled to all
                        rights and privileges thereof.
                    @endif
                </div>

                <!-- Signatories -->
                @if($signatories && count($signatories) > 0)
                    <div class="signatories-section">
                        @foreach($signatories as $signatory)
                            <div class="signatory-box">
                                @if($signatory->signature_url && file_exists(public_path($signatory->signature_url)))
                                    <img src="{{ public_path($signatory->signature_url) }}" alt="Signature" class="signature-image">
                                @else

                                    <div class="signature-placeholder"></div>
                                @endif
                                <div class="signature-line"></div>
                                <div class="signatory-name">{{ strtoupper($signatory->name) }}</div>
                                @if($signatory->role)
                                    <div class="signatory-role">{{ $signatory->role }}</div>
                                @endif
                                @if($signatory->signed_date)
                                    <div class="signatory-date">{{ \Carbon\Carbon::parse($signatory->signed_date)->format('F d, Y')
                                        }}</div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>

            <!-- Footer -->
            <div class="certificate-footer">
                <div class="certificate-number">Certificate No. TGFC-{{ str_pad($legitimacy->id, 6, '0', STR_PAD_LEFT)
                    }}</div>
                <div class="footer-line">Issued on {{ $generatedDate }}</div>
                <div class="footer-line">© {{ date('Y') }} Triskelion Grand Fraternity. All rights reserved.</div>
            </div>
        </div>
    </div>
</body>

</html>