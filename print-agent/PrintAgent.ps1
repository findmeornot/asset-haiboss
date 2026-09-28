# PrintAgent.ps1
#
# Polling agent buat printer TSC TE210 (USB, driver Seagull) yang jalan di
# PC tempat printer nempel. Web Laravel (Haiboss) bisa diakses dari PC mana
# aja, tapi printer cuma bisa dicetak dari PC ini -- makanya pola queue +
# polling: web bikin PrintJob row (status=pending), script ini polling
# tiap N detik, ambil job punya station ini, generate TSPL2, kirim RAW ke
# printer, lapor balik status ke Laravel.
#
# Layout & watermark bitmap di bawah SUDAH CONFIRMED lewat banyak iterasi
# cetak fisik (asal: send-asset-label-full-preview.ps1 v14). JANGAN diubah
# kecuali emang diminta -- termasuk margin, posisi Y, dan data bitmap.
#
# Cara jalanin persistent di background (dari paling simpel):
#   1) Task Scheduler (PALING SIMPEL, gak perlu install apa-apa):
#      - Buka Task Scheduler > Create Task
#      - Trigger: "At log on" (atau "At startup" kalau mau jalan tanpa login)
#      - Action: Start a program
#          Program: powershell.exe
#          Arguments: -ExecutionPolicy Bypass -WindowStyle Hidden -File "C:\path\PrintAgent.ps1"
#      - Tab Settings: centang "If the task fails, restart every: 1 minute",
#        set "Attempt to restart up to: " ke angka besar (mis. 999).
#      - Ini udah cukup buat auto-restart kalau script crash/PC restart.
#   2) NSSM (https://nssm.cc/) kalau mau jadi Windows Service beneran
#      (jalan meski gak ada user login sama sekali, restart lebih reliable):
#      nssm install PrintAgent powershell.exe
#      nssm set PrintAgent AppParameters "-ExecutionPolicy Bypass -File C:\path\PrintAgent.ps1"
#      nssm set PrintAgent AppExit Default Restart
#      nssm start PrintAgent
#   Rekomendasi: mulai dari opsi 1 (Task Scheduler), upgrade ke NSSM cuma
#   kalau PC-nya emang jarang ada yang login (mis. PC gudang yang nyala
#   terus tapi jarang dipakai interaktif).

# ============================== CONFIG ==============================
$LaravelBaseUrl = "https://haiboss.example.com/api/v1"  # <-- ganti sesuai domain WMS, TANPA trailing slash
$StationKey     = "gudang-1"                             # <-- harus sama persis dgn station_key pas `printer:create-station`
$ApiToken       = "RXy62XTBseG66KrXNpvB5fGwI37qx6jE19YHvhlZaV3o86ECMTmzJCsrdQ4vtNlH"
$PrinterName    = "TSC TE210"                             # <-- harus persis sama dgn nama di Devices and Printers

$PollIntervalSeconds      = 2
$HeartbeatIntervalSeconds = 30
# ======================================================================

$Headers = @{ Authorization = "Bearer $ApiToken" }

# ---------------------------------------------------------------------
# Watermark "UNIVERSITAS STEKOM" -- CONFIRMED lewat trial-error fisik,
# JANGAN diubah. Bitmap A dibaca bawah->atas (tepi KIRI tiap kartu),
# bitmap B dibaca atas->bawah/mirror (tepi KANAN tiap kartu). Polaritas
# bit: 0=hitam(cetak), 1=putih(kosong) -- sudah baked-in di data ini.
# ---------------------------------------------------------------------
$WatermarkA_Base64 = "/////4B/gH+P/+P/8f/j/4//gH+Af///4f/A/4x/v3+/f55/gH/A////v3+ef8z/wf/j/4B/gH//////v3+3f7d/t3+Af4B///+//7//gH+Af7//v/+/////mP+wf7N/s3+Df8Z/////////////////mP+wf7N/s3+Df8Z///////x/8H+B/43/hf/A//h/v3+//4B/gH+//7//v////4B/gH//////mP+wf7N/s3+Df8Z///////5/xH+A/7v/u/+Af4B///+/f7d/t3+3f4B/gH//////j/+D/+B//H/4f8D/h/+/////gH+Af/////+Af4B/+P/j/4f/gH+Af/////+A/4B//n//f/5/gH+A//////8="
$WatermarkB_Base64 = "//////8B/gH+f/7//n/+Af8B//////4B/gH/4f/H/x/+Af4B//////4B/gH////9/+H/A/4f/j/+B//B//H//////gH+Af7t/u3+7f79///+Af4B/93/3f8B/iP+f//////+Y/7B/s3+zf4N/xn//////gH+Af////3//f/9/gH+Af/9/v3+H/8D/6H/sf+B/g/+P//////+Y/7B/s3+zf4N/xn////////////////+Y/7B/s3+zf4N/xn////9//3//f4B/gH//f/9///+Af4B/u3+7f7t/v3//////gH+Af/H/4P/M/55/v3///8D/gH+ef79/v3+Mf8D/4f///4B/gH/8f/H/4//x//x/gH+Af////8="
$WM_WidthBytes = 2    # 16 dot lebar
$WM_HeightDots = 139
$WM_Y = 13            # posisi Y watermark, JANGAN diubah -- udah mepet ke batas fisik printer

$WMA_Bytes = [System.Convert]::FromBase64String($WatermarkA_Base64)
$WMB_Bytes = [System.Convert]::FromBase64String($WatermarkB_Base64)

if ($WMA_Bytes.Length -ne ($WM_WidthBytes * $WM_HeightDots) -or $WMB_Bytes.Length -ne ($WM_WidthBytes * $WM_HeightDots)) {
    throw "Bitmap watermark corrupt: panjang byte tidak sesuai $($WM_WidthBytes * $WM_HeightDots) yang dideklarasikan. Jangan lanjut print, cek ulang base64-nya."
}

# Media: roll 82mm, 2 label per baris @ 40mm x 20mm, gap 2mm horizontal.
$RightCellOffsetX = 336  # 40mm (lebar sel) + 2mm (gap) = 336 dot @203DPI

# ---------------------------------------------------------------------
# Win32 raw printing (OpenPrinter/StartDocPrinter/WritePrinter), pDataType=RAW.
# ---------------------------------------------------------------------
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;

public class RawPrinterHelper
{
    [StructLayout(LayoutKind.Sequential, CharSet = CharSet.Ansi)]
    public class DOCINFOA
    {
        [MarshalAs(UnmanagedType.LPStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPStr)] public string pDataType;
    }

    [DllImport("winspool.Drv", EntryPoint = "OpenPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool OpenPrinter(string szPrinter, out IntPtr hPrinter, IntPtr pd);

    [DllImport("winspool.Drv", EntryPoint = "ClosePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool ClosePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartDocPrinterA", SetLastError = true, CharSet = CharSet.Ansi, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool StartDocPrinter(IntPtr hPrinter, Int32 level, [In, MarshalAs(UnmanagedType.LPStruct)] DOCINFOA di);

    [DllImport("winspool.Drv", EntryPoint = "EndDocPrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndDocPrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "StartPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool StartPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "EndPagePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool EndPagePrinter(IntPtr hPrinter);

    [DllImport("winspool.Drv", EntryPoint = "WritePrinter", SetLastError = true, ExactSpelling = true, CallingConvention = CallingConvention.StdCall)]
    public static extern bool WritePrinter(IntPtr hPrinter, IntPtr pBytes, Int32 dwCount, out Int32 dwWritten);

    public static bool SendBytesToPrinter(string szPrinterName, IntPtr pBytes, Int32 dwCount)
    {
        IntPtr hPrinter;
        DOCINFOA di = new DOCINFOA();
        Int32 dwWritten = 0;
        bool bSuccess = false;
        di.pDocName = "Haiboss PrintAgent - label aset";
        di.pDataType = "RAW";

        if (OpenPrinter(szPrinterName.Normalize(), out hPrinter, IntPtr.Zero))
        {
            if (StartDocPrinter(hPrinter, 1, di))
            {
                if (StartPagePrinter(hPrinter))
                {
                    bSuccess = WritePrinter(hPrinter, pBytes, dwCount, out dwWritten);
                    EndPagePrinter(hPrinter);
                }
                EndDocPrinter(hPrinter);
            }
            ClosePrinter(hPrinter);
        }
        return bSuccess;
    }
}
"@

function Send-RawToPrinter {
    param([byte[]]$Bytes)

    $pUnmanagedBytes = [System.Runtime.InteropServices.Marshal]::AllocCoTaskMem($Bytes.Length)
    try {
        [System.Runtime.InteropServices.Marshal]::Copy($Bytes, 0, $pUnmanagedBytes, $Bytes.Length)
        return [RawPrinterHelper]::SendBytesToPrinter($PrinterName, $pUnmanagedBytes, $Bytes.Length)
    } finally {
        [System.Runtime.InteropServices.Marshal]::FreeCoTaskMem($pUnmanagedBytes)
    }
}

# ---------------------------------------------------------------------
# Generate TSPL2 buat 1 baris (1 atau 2 label). Margin/koordinat persis
# sama kayak send-asset-label-full-preview.ps1 v14 -- termasuk pembagian
# InternalRightMargin (celah antar 2 label) vs TrueEdgeRightMargin (tepi
# asli kertas), yang beda dari margin kiri karena bias fisik print head.
# ---------------------------------------------------------------------
function Build-LabelRowBytes {
    param([array]$Jobs)  # 1-2 item, tiap item punya .inventory_number & .location_label

    $parts = New-Object System.Collections.Generic.List[object]
    function Add-Text($s) { $parts.Add([System.Text.Encoding]::ASCII.GetBytes($s)) }
    function Add-Bytes($b) { $parts.Add($b) }

    Add-Text "SIZE 82 mm,20 mm`r`nGAP 2 mm,0 mm`r`nDIRECTION 1`r`nCLS`r`n"

    $offsets = @(0, $RightCellOffsetX)

    for ($i = 0; $i -lt $Jobs.Count; $i++) {
        $job = $Jobs[$i]
        $offsetX = $offsets[$i]
        $isRightmostCell = ($i -eq 1)  # posisi kanan = tepi asli kertas, margin lebih ketat

        $LeftSideMargin = 14           # CONFIRMED PAS, JANGAN DIUBAH
        $InternalRightMargin = 1       # celah antar 2 label
        $TrueEdgeRightMargin = 0       # tepi asli kertas -- sudah di batas mutlak
        $RightSideMargin = if ($isRightmostCell) { $TrueEdgeRightMargin } else { $InternalRightMargin }

        $WmWidthPx = $WM_WidthBytes * 8
        $Pad = 10

        $leftEdgeX  = $offsetX + $LeftSideMargin
        $rightEdgeX = $offsetX + 320 - $WmWidthPx - $RightSideMargin

        $safeStart   = $LeftSideMargin + $WmWidthPx + $Pad
        $safeEnd     = (320 - $WmWidthPx - $RightSideMargin) - $Pad
        $safeWidth   = $safeEnd - $safeStart
        $safeCenterX = $offsetX + $safeStart + [int]($safeWidth / 2)
        $bandX       = $offsetX + $safeStart

        # watermark tepi kiri label (baca bawah->atas)
        Add-Text "BITMAP $leftEdgeX,$WM_Y,$WM_WidthBytes,$WM_HeightDots,0,"
        Add-Bytes $WMA_Bytes
        Add-Text "`r`n"

        # watermark tepi kanan label (baca atas->bawah, mirror)
        Add-Text "BITMAP $rightEdgeX,$WM_Y,$WM_WidthBytes,$WM_HeightDots,0,"
        Add-Bytes $WMB_Bytes
        Add-Text "`r`n"

        # lokasi -- alignment=2 (printer sendiri yang center, jangan hitung manual)
        Add-Text "TEXT $safeCenterX,13,`"1`",0,1,1,2,`"$($job.location_label)`"`r`n"

        # barcode Code128 -- checksum auto, kompatibel sama Picqer di web
        Add-Text "BARCODE $safeCenterX,36,`"128`",70,0,0,2,2,2,`"$($job.inventory_number)`"`r`n"

        # band SKU: teks dulu (center otomatis), baru REVERSE jadi putih-di-atas-hitam
        Add-Text "TEXT $safeCenterX,124,`"2`",0,1,1,2,`"$($job.inventory_number)`"`r`n"
        Add-Text "REVERSE $bandX,116,$safeWidth,36`r`n"
    }

    Add-Text "PRINT 1`r`n"

    $totalLength = 0
    foreach ($p in $parts) { $totalLength += $p.Length }
    $bytes = New-Object byte[] $totalLength
    $offset = 0
    foreach ($p in $parts) {
        [Array]::Copy($p, 0, $bytes, $offset, $p.Length)
        $offset += $p.Length
    }
    return $bytes
}

# ---------------------------------------------------------------------
# HTTP helper ke Laravel
# ---------------------------------------------------------------------
function Invoke-Agent {
    param(
        [Parameter(Mandatory)] [string]$Method,
        [Parameter(Mandatory)] [string]$Path,
        $Body = $null
    )

    $uri = "$LaravelBaseUrl$Path"
    if ($null -ne $Body) {
        return Invoke-RestMethod -Method $Method -Uri $uri -Headers $Headers -ContentType "application/json" -Body ($Body | ConvertTo-Json)
    }
    return Invoke-RestMethod -Method $Method -Uri $uri -Headers $Headers
}

# ---------------------------------------------------------------------
# Main loop
# ---------------------------------------------------------------------
Write-Host "PrintAgent started. Station=$StationKey Printer=$PrinterName BaseUrl=$LaravelBaseUrl"
$lastHeartbeat = [DateTime]::MinValue

while ($true) {
    try {
        if (([DateTime]::Now - $lastHeartbeat).TotalSeconds -ge $HeartbeatIntervalSeconds) {
            try {
                Invoke-Agent -Method Post -Path "/agent/stations/heartbeat" | Out-Null
            } catch {
                Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Heartbeat gagal: $($_.Exception.Message)"
            }
            $lastHeartbeat = [DateTime]::Now
        }

        # ?station= cuma buat kemudahan baca log server -- identitas station
        # sebenarnya ditentukan dari Bearer token, bukan dari query string ini.
        $resp = Invoke-Agent -Method Get -Path "/agent/print-jobs/pending?station=$StationKey"
        $jobs = @($resp.jobs)

        if ($jobs.Count -gt 0) {
            $names = ($jobs | ForEach-Object { $_.inventory_number }) -join ', '
            Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Dapat $($jobs.Count) job: $names"

            try {
                $bytes = Build-LabelRowBytes -Jobs $jobs
                $ok = Send-RawToPrinter -Bytes $bytes

                if ($ok) {
                    foreach ($job in $jobs) {
                        Invoke-Agent -Method Post -Path "/agent/print-jobs/$($job.id)/complete" | Out-Null
                    }
                    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Sukses cetak $($jobs.Count) label."
                } else {
                    foreach ($job in $jobs) {
                        try {
                            Invoke-Agent -Method Post -Path "/agent/print-jobs/$($job.id)/fail" -Body @{ error_message = "WritePrinter gagal (SendBytesToPrinter return false). Cek printer nyala/online/nama persis '$PrinterName'." } | Out-Null
                        } catch {}
                    }
                    Write-Host "[$(Get-Date -Format 'HH:mm:ss')] GAGAL kirim ke printer '$PrinterName'."
                }
            } catch {
                $msg = $_.Exception.Message
                Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Error saat generate/print: $msg"
                foreach ($job in $jobs) {
                    try {
                        Invoke-Agent -Method Post -Path "/agent/print-jobs/$($job.id)/fail" -Body @{ error_message = $msg } | Out-Null
                    } catch {}
                }
            }
        }
    } catch {
        Write-Host "[$(Get-Date -Format 'HH:mm:ss')] Poll error: $($_.Exception.Message)"
    }

    Start-Sleep -Seconds $PollIntervalSeconds
}
