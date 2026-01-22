<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Citizens Management Report</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'DejaVu Sans', Arial, sans-serif;
            font-size: 10px;
            color: #000;
            padding: 20px;
        }

        .header-container {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .header-left {
            display: table-cell;
            width: 70%;
            vertical-align: top;
            padding-right: 20px;
        }

        .header-right {
            display: table-cell;
            width: 30%;
            text-align: right;
            vertical-align: top;
        }

        .logo {
            width: 100px;
            height: 100px;
        }

        .date-time {
            font-size: 9px;
            margin-bottom: 10px;
        }

        .institution-name {
            font-size: 11px;
            font-weight: bold;
            margin-bottom: 3px;
            color: #1a5490;
        }

        .report-title {
            font-size: 13px;
            font-weight: bold;
            margin: 15px 0 5px 0;
            text-align: center;
        }

        .report-subtitle {
            font-size: 10px;
            text-align: center;
            margin-bottom: 15px;
        }

        .info-section {
            border: 1px solid #000;
            padding: 10px;
            margin-bottom: 15px;
        }

        .info-row {
            display: table;
            width: 100%;
            margin-bottom: 5px;
        }

        .info-label {
            display: table-cell;
            width: 30%;
            font-weight: bold;
            padding: 3px 5px;
        }

        .info-value {
            display: table-cell;
            width: 70%;
            padding: 3px 5px;
            border-bottom: 1px solid #ccc;
        }

        .stats-grid {
            display: table;
            width: 100%;
            border: 1px solid #000;
            margin-bottom: 15px;
        }

        .stats-row {
            display: table-row;
        }

        .stats-cell {
            display: table-cell;
            padding: 8px;
            border: 1px solid #000;
            text-align: center;
            width: 20%;
        }

        .stats-label {
            font-size: 8px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }

        .stats-value {
            font-size: 16px;
            font-weight: bold;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border: 1px solid #000;
        }

        .data-table th {
            background-color: #1a5490;
            color: white;
            padding: 8px 5px;
            text-align: left;
            font-size: 8px;
            font-weight: bold;
            border: 1px solid #000;
        }

        .data-table td {
            padding: 6px 5px;
            border: 1px solid #000;
            font-size: 8px;
        }

        .data-table tbody tr:nth-child(even) {
            background-color: #f5f5f5;
        }

        .status-badge {
            display: inline-block;
            padding: 2px 8px;
            border-radius: 3px;
            font-size: 7px;
            font-weight: bold;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-approved {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .status-deactivated {
            background-color: #e5e7eb;
            color: #1f2937;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8px;
            color: #666;
            border-top: 1px solid #000;
            padding: 10px 20px;
            background-color: white;
        }

        .page-number {
            position: fixed;
            bottom: 35px;
            right: 20px;
            font-size: 9px;
        }
    </style>
</head>
<body>
    <!-- Header Section -->
    <div class="header-container">
        <div class="header-left">
            <div class="date-time">{{ $generatedDateTime }}</div>
            <div class="institution-name">CITIZENS MANAGEMENT SYSTEM</div>
            <div class="institution-name" style="font-size: 10px; color: #000;">Community Registration Department</div>
        </div>
        <div class="header-right">
            @if(file_exists(public_path('images/logo.png')))
                <img src="{{ public_path('images/logo.png') }}" alt="Logo" class="logo">
            @else
                <div style="width: 100px; height: 100px; border: 2px solid #1a5490; display: inline-block;"></div>
            @endif
        </div>
    </div>

    <!-- Report Title -->
    <div class="report-title">Master Report</div>
    <div class="report-subtitle">User Registration and Status Overview</div>

    <!-- Report Information -->
    <div class="info-section">
        <div class="info-row">
            <div class="info-label">Report Type:</div>
            <div class="info-value">User Management Report</div>
        </div>
        <div class="info-row">
            <div class="info-label">Generated On:</div>
            <div class="info-value">{{ $date }} at {{ $time }}</div>
        </div>
        <div class="info-row">
            <div class="info-label">Total Records:</div>
            <div class="info-value">{{ $stats['total'] }}</div>
        </div>
    </div>

    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stats-row">
            <div class="stats-cell">
                <div class="stats-label">Total Users</div>
                <div class="stats-value" style="color: #1a5490;">{{ $stats['total'] }}</div>
            </div>
            <div class="stats-cell">
                <div class="stats-label">Pending</div>
                <div class="stats-value" style="color: #d97706;">{{ $stats['pending'] }}</div>
            </div>
            <div class="stats-cell">
                <div class="stats-label">Approved</div>
                <div class="stats-value" style="color: #059669;">{{ $stats['approved'] }}</div>
            </div>
            <div class="stats-cell">
                <div class="stats-label">Rejected</div>
                <div class="stats-value" style="color: #dc2626;">{{ $stats['rejected'] }}</div>
            </div>
            <div class="stats-cell">
                <div class="stats-label">Deactivated</div>
                <div class="stats-value" style="color: #6b7280;">{{ $stats['deactivated'] }}</div>
            </div>
        </div>
    </div>

    <!-- User Data Table -->
    @if($users->count() > 0)
        <table class="data-table">
            <thead>
                <tr>
                    <th style="width: 5%;">NO</th>
                    <th style="width: 18%;">Full Name</th>
                    <th style="width: 20%;">Email Address</th>
                    <th style="width: 12%;">Phone Number</th>
                    <th style="width: 18%;">Address</th>
                    <th style="width: 10%;">Frat. No.</th>
                    <th style="width: 10%;">Status</th>
                    <th style="width: 12%;">Registered</th>
                </tr>
            </thead>
            <tbody>
                @foreach($users as $index => $user)
                    <tr>
                        <td>{{ $index+1 }}</td>
                        <td>{{ $user->name }}</td>
                        <td>{{ $user->email }}</td>
                        <td>{{ $user->phone_number ?? 'N/A' }}</td>
                        <td>{{ Str::limit($user->address ?? 'N/A', 40) }}</td>
                        <td>{{ $user->fraternity_number ?? 'N/A' }}</td>
                        <td>
                            <span class="status-badge status-{{ $user->status }}">
                                {{ strtoupper($user->status) }}
                            </span>
                        </td>
                        <td>{{ $user->created_at->format('M d, Y') }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <div style="text-align: center; padding: 40px; border: 1px solid #ccc;">
            <p style="font-weight: bold;">No users found</p>
            <p>Try adjusting your filters or search criteria.</p>
        </div>
    @endif

    <!-- Footer -->
    <div class="footer">
        <p>This report was automatically generated by the Citizens Management System</p>
        <p>© {{ date('Y') }} All rights reserved. | Confidential Document</p>
    </div>

    <!-- Page Number -->
    <div class="page-number">Page 1 of 1</div>
</body>
</html>