<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database Backup {{ $results['success'] ? 'Completed' : 'Failed' }}</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif;
            line-height: 1.6;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
        }
        .container {
            max-width: 600px;
            margin: 20px auto;
            padding: 20px;
            background: #ffffff;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        .header {
            text-align: center;
            padding: 20px 0;
            border-bottom: 1px solid #eee;
        }
        .content {
            padding: 20px 0;
        }
        .footer {
            text-align: center;
            color: #666;
            font-size: 14px;
            padding-top: 20px;
            border-top: 1px solid #eee;
        }
        .button {
            display: inline-block;
            padding: 12px 24px;
            background-color: #4F46E5;
            color: white;
            text-decoration: none;
            border-radius: 4px;
            margin: 20px 0;
        }
        .text-content {
            unicode-bidi: plaintext;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 4px;
            font-weight: bold;
            margin: 10px 0;
        }
        .status-success {
            background-color: #10B981;
            color: white;
        }
        .status-failure {
            background-color: #EF4444;
            color: white;
        }
        .card {
            border: 1px solid #eee;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
        }
        .card-title {
            font-size: 16px;
            font-weight: bold;
            border-bottom: 1px solid #eee;
            padding-bottom: 10px;
            margin-top: 0;
            margin-bottom: 15px;
        }
        .info-row {
            margin-bottom: 8px;
        }
        .info-label {
            font-weight: bold;
            display: inline-block;
            width: 40%;
            vertical-align: top;
        }
        .info-value {
            display: inline-block;
            width: 58%;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }
        .stat-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            text-align: center;
            border: 1px solid #eee;
        }
        .stat-value {
            font-size: 24px;
            font-weight: bold;
            margin: 5px 0;
        }
        .stat-label {
            font-size: 14px;
            color: #666;
        }
        .tag-container {
            margin: 10px 0;
        }
        .tag {
            display: inline-block;
            background-color: #E5E7EB;
            color: #374151;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 13px;
            margin-right: 5px;
            margin-bottom: 5px;
        }
        .alert {
            padding: 12px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
            font-size: 14px;
        }
        .alert-success {
            background-color: #ECFDF5;
            border: 1px solid #10B981;
            color: #065F46;
        }
        .alert-danger {
            background-color: #FEF2F2;
            border: 1px solid #EF4444;
            color: #991B1B;
        }
        .alert-warning {
            background-color: #FFFBEB;
            border: 1px solid #F59E0B;
            color: #92400E;
        }
        .list-item {
            padding: 8px 12px;
            border-left: 3px solid #eee;
            margin-bottom: 8px;
            background-color: #f9f9f9;
        }
        .list-item-error {
            border-left-color: #EF4444;
        }
        .list-item-warning {
            border-left-color: #F59E0B;
        }
        .text-center {
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 class="text-content">Database Backup {{ $results['success'] ? 'Completed' : 'Failed' }}</h1>
        </div>
        
        <div class="content">
            <div class="alert {{ $results['success'] ? 'alert-success' : 'alert-danger' }}">
                {{ $results['message'] }}
            </div>
            
            <div class="card">
                <h2 class="card-title">Backup Information</h2>
                <div class="info-row">
                    <span class="info-label">Backup Name:</span>
                    <span class="info-value">{{ $backupName }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Database:</span>
                    <span class="info-value">{{ $databaseName }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Environment:</span>
                    <span class="info-value">{{ $environment }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Date & Time:</span>
                    <span class="info-value">{{ $timestamp }}</span>
                </div>
                <div class="info-row">
                    <span class="info-label">Status:</span>
                    <span class="info-value">
                        <span class="status-badge {{ $results['success'] ? 'status-success' : 'status-failure' }}">
                            {{ $results['success'] ? 'Success' : 'Failed' }}
                        </span>
                    </span>
                </div>
            </div>
            
            <div class="card">
                <h2 class="card-title">Backup Summary</h2>
                <div class="stats-grid">
                    <div class="stat-box">
                        <div class="stat-value">{{ $results['stats']['tables_exported'] }}</div>
                        <div class="stat-label">Tables Exported</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">{{ $results['stats']['rows_exported'] }}</div>
                        <div class="stat-label">Rows Exported</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">{{ $results['stats']['tables_skipped'] }}</div>
                        <div class="stat-label">Tables Skipped</div>
                    </div>
                    <div class="stat-box">
                        <div class="stat-value">{{ isset($results['stats']['zip_size']) ? $results['stats']['zip_size'] . ' MB' : 'N/A' }}</div>
                        <div class="stat-label">Backup Size</div>
                    </div>
                </div>
            </div>
            
            @if(!empty($results['errors']))
            <div class="card">
                <h2 class="card-title">Errors</h2>
                @foreach($results['errors'] as $error)
                <div class="list-item list-item-error">{{ $error }}</div>
                @endforeach
            </div>
            @endif
            
            @if(!empty($results['warnings']))
            <div class="card">
                <h2 class="card-title">Warnings</h2>
                @foreach($results['warnings'] as $warning)
                <div class="list-item list-item-warning">{{ $warning }}</div>
                @endforeach
            </div>
            @endif
            
            @if($results['stats']['tables_exported'] > 0)
            <div class="card">
                <h2 class="card-title">Exported Tables</h2>
                <div class="tag-container">
                    @foreach($results['stats']['tables_list'] as $table)
                    <span class="tag">{{ $table }}</span>
                    @endforeach
                </div>
            </div>
            @endif
            
            @if(!empty($results['stats']['excluded_tables']))
            <div class="card">
                <h2 class="card-title">Excluded Tables</h2>
                <div class="tag-container">
                    @foreach($results['stats']['excluded_tables'] as $table)
                    <span class="tag">{{ $table }}</span>
                    @endforeach
                </div>
            </div>
            @endif
            
            <div class="text-center">
                <a href="{{ config('app.url') }}" class="button">View Application</a>
            </div>
        </div>
        
        <div class="footer">
            <p class="text-content">This is an automated message from the Laravel DB Seeder Export package</p>
            <p class="text-content">&copy; {{ date('Y') }} {{ config('app.name') }}</p>
        </div>
    </div>
</body>
</html>