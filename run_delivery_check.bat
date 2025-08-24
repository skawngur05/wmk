@echo off
REM Wrap My Kitchen - Delivery Status Check Script
REM This batch file runs the automated delivery check
REM Schedule this to run every hour via Windows Task Scheduler

echo ===========================================
echo Wrap My Kitchen - Delivery Status Check
echo ===========================================
echo %date% %time%

REM Change to the correct directory
cd /d "C:\xampp\htdocs\wmk"

REM Run the PHP delivery check script
"C:\xampp\php\php.exe" schedule_delivery_check.php

REM Check if the script ran successfully
if %errorlevel% equ 0 (
    echo SUCCESS: Delivery check completed successfully
) else (
    echo ERROR: Delivery check failed with error code %errorlevel%
)

echo ===========================================
echo Completed at %date% %time%
echo ===========================================

REM Uncomment the line below if you want to pause and see output when run manually
REM pause
