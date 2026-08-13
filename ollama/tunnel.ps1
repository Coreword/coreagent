# Publishes this machine's Ollama to the server over an SSH reverse tunnel, and
# reconnects when the link drops.
#
#   powershell -ExecutionPolicy Bypass -File ollama\tunnel.ps1
#
# The remote end binds to 127.0.0.1, so the server reaches Ollama on its own
# loopback and nothing is exposed to the internet. Port 11435 on the server
# rather than 11434, because the server runs its own Ollama on 11434 and that
# one is the fallback when this tunnel is down.

param(
    [string]$RemoteHost = 'contabo-coreword',
    [int]$RemotePort = 11435,
    [int]$LocalPort = 11434,
    [int]$RetrySeconds = 10
)

$ollama = Join-Path $env:LOCALAPPDATA 'Programs\Ollama\ollama.exe'

while ($true) {
    # No point holding a tunnel open to a dead Ollama: the server would get a
    # connection that accepts and then fails, which is slower to fall through
    # than a refused one.
    try {
        Invoke-WebRequest -Uri "http://127.0.0.1:$LocalPort/api/tags" -UseBasicParsing -TimeoutSec 5 | Out-Null
    } catch {
        Write-Host "[tunnel] local Ollama not responding on $LocalPort, starting it"
        if (Test-Path $ollama) {
            Start-Process -FilePath $ollama -ArgumentList 'serve' -WindowStyle Hidden
            Start-Sleep -Seconds 5
        }
    }

    Write-Host "[tunnel] connecting: $RemoteHost 127.0.0.1:$RemotePort -> 127.0.0.1:$LocalPort"

    # ServerAliveInterval makes a half-open link fail rather than hang, which is
    # the failure mode that would otherwise leave the server waiting on a tunnel
    # that is technically connected but carrying nothing.
    ssh -N `
        -o BatchMode=yes `
        -o ExitOnForwardFailure=yes `
        -o ServerAliveInterval=30 `
        -o ServerAliveCountMax=3 `
        -R "127.0.0.1:${RemotePort}:127.0.0.1:${LocalPort}" `
        $RemoteHost

    Write-Host "[tunnel] disconnected (exit $LASTEXITCODE), retrying in ${RetrySeconds}s"
    Start-Sleep -Seconds $RetrySeconds
}
