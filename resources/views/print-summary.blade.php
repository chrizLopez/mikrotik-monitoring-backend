<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>MikroTik Monitoring Summary</title>
    <style>
        body { font-family: Arial, sans-serif; color: #0f172a; margin: 24px; }
        h1, h2 { margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 24px; }
        th, td { border: 1px solid #cbd5e1; padding: 8px; font-size: 12px; text-align: left; }
        .meta { color: #475569; margin-bottom: 24px; }
    </style>
</head>
<body>
    <h1>MikroTik Monitoring Summary</h1>
    <p class="meta">Range: {{ $range }}</p>

    <h2>Top Consumers</h2>
    <table>
        <thead>
            <tr>
                <th>Name</th>
                <th>Group</th>
                <th>Total Bytes</th>
                <th>Usage %</th>
                <th>State</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['top_users'] ?? []) as $user)
                <tr>
                    <td>{{ $user['name'] }}</td>
                    <td>{{ $user['group_name'] }}</td>
                    <td>{{ $user['total_bytes'] }}</td>
                    <td>{{ $user['usage_percent'] }}</td>
                    <td>{{ $user['state'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>ISP Totals</h2>
    <table>
        <thead>
            <tr>
                <th>ISP</th>
                <th>Interface</th>
                <th>Total Bytes</th>
                <th>Share %</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['isp_distribution']['items'] ?? []) as $isp)
                <tr>
                    <td>{{ $isp['name'] }}</td>
                    <td>{{ $isp['interface_name'] }}</td>
                    <td>{{ $isp['total_bytes'] }}</td>
                    <td>{{ $isp['share_percent'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>

    <h2>Quota Alerts</h2>
    <table>
        <thead>
            <tr>
                <th>Severity</th>
                <th>Title</th>
                <th>Subject</th>
            </tr>
        </thead>
        <tbody>
            @foreach(($report['alerts']['quota_alerts'] ?? []) as $alert)
                <tr>
                    <td>{{ $alert['severity'] }}</td>
                    <td>{{ $alert['title'] }}</td>
                    <td>{{ $alert['subject'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
