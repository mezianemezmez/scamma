<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReferrerAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Skip middleware for API routes with authentication (your React app)
        if ($request->is('api/*') && $request->bearerToken()) {
            return $next($request);
        }

        // Skip middleware for CSRF cookie requests
        if ($request->is('sanctum/csrf-cookie')) {
            return $next($request);
        }

        // Get the current domain dynamically
        $currentDomain = parse_url($request->root(), PHP_URL_HOST);

        // Validate referrer dynamically based on current domain
        $referrer = $request->header('Referer');
        if (!$this->isValidReferrer($referrer, $currentDomain)) {
            Log::warning('Invalid referrer detected', [
                'referrer' => $referrer,
                'currentDomain' => $currentDomain,
                'ip' => $request->ip(),
                'user_agent' => $request->header('User-Agent')
            ]);
            

            
            // Return JSON response for API requests, redirect for web requests
            if ($request->expectsJson() || $request->is('api/*')) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied',
                    'error' => 'Invalid referrer'
                ], 403);
            }
            
            return redirect()->route('client.404');
        }

        return $next($request);
    }

    /**
     * Validate the referrer against the current domain.
     *
     * @param string|null $referrer
     * @param string $currentDomain
     * @return bool
     */
private function isValidReferrer(?string $referrer, string $currentDomain): bool
{
    // Allow requests with no referrer (e.g., direct visits)
    if (!$referrer) {
        //Log::debug('Request allowed due to no referrer (direct browser visit).');
        return true;
    }

    // List of blocked host domains
    $blockedDomains = [
        // Existing domains
        'phishtank.com',
        'www.phishtank.com',
        'namecheap.com',
        'www.namecheap.com',
        'malwaredomainlist.com',
        'www.malwaredomainlist.com',
        'spamhaus.org',
        'www.spamhaus.org',
        'openphish.com',
        'www.openphish.com',
        'sucuri.net',
        'www.sucuri.net',
        'virustotal.com',
        'www.virustotal.com',
        'scamadviser.com',
        'www.scamadviser.com',
        'urlvoid.com',
        'www.urlvoid.com',
        'stopbadware.org',
        'www.stopbadware.org',
        'googleweblight.com',
        'www.googleweblight.com',
        'whois.domaintools.com',
        'www.domaintools.com',
        'talosintelligence.com',
        'www.talosintelligence.com',
        'fortiguard.com',
        'www.fortiguard.com',
        'ipvoid.com',
        'www.ipvoid.com',
        'cymon.io',
        'www.cymon.io',
        'dnsbl.info',
        'www.dnsbl.info',
        'threatminer.org',
        'www.threatminer.org',
        'kaspersky.com',
        'www.kaspersky.com',
        'paloaltonetworks.com',
        'www.paloaltonetworks.com',
        // Additional Security & Malware Analysis Sites
        'hybrid-analysis.com',
        'www.hybrid-analysis.com',
        'joesandbox.com',
        'www.joesandbox.com',
        'any.run',
        'www.any.run',
        'app.any.run',
        'malwr.com',
        'www.malwr.com',
        'cuckoo.cert.ee',
        'malware-traffic-analysis.net',
        'www.malware-traffic-analysis.net',
        // Domain Intelligence & Research
        'securitytrails.com',
        'www.securitytrails.com',
        'riskiq.com',
        'www.riskiq.com',
        'passivetotal.org',
        'www.passivetotal.org',
        'recordedfuture.com',
        'www.recordedfuture.com',
        'threatconnect.com',
        'www.threatconnect.com',
        'anomali.com',
        'www.anomali.com',
        'exchange.xforce.ibmcloud.com',
        'otx.alienvault.com',
        'cybercrime-tracker.net',
        'www.cybercrime-tracker.net',
        // Scanning & Reconnaissance Tools
        'shodan.io',
        'www.shodan.io',
        'censys.io',
        'www.censys.io',
        'search.censys.io',
        'binaryedge.io',
        'www.binaryedge.io',
        'zoomeye.org',
        'www.zoomeye.org',
        'fofa.so',
        'www.fofa.so',
        'criminalip.io',
        'www.criminalip.io',
        'greynoise.io',
        'www.greynoise.io',
        'viz.greynoise.io',
        // SSL/TLS & Security Testing
        'ssllabs.com',
        'www.ssllabs.com',
        'observatory.mozilla.org',
        'securityheaders.com',
        'www.securityheaders.com',
        'hardenize.com',
        'www.hardenize.com',
        'immuniweb.com',
        'www.immuniweb.com',
        // Phishing & Brand Protection
        'netcraft.com',
        'www.netcraft.com',
        'toolbar.netcraft.com',
        'phishing.org',
        'www.phishing.org',
        'anti-phishing.org',
        'www.anti-phishing.org',
        'markmonitor.com',
        'www.markmonitor.com',
        'brandshield.com',
        'www.brandshield.com',
        'redpoints.com',
        'www.redpoints.com',
        'phishlabs.com',
        'www.phishlabs.com',
        // Threat Intelligence Feeds
        'malwaredomains.com',
        'www.malwaredomains.com',
        'malc0de.com',
        'www.malc0de.com',
        'zeustracker.abuse.ch',
        'feodotracker.abuse.ch',
        'sslbl.abuse.ch',
        'urlhaus.abuse.ch',
        'bazaar.abuse.ch',
        'threatfox.abuse.ch',
        'misp-project.org',
        'www.misp-project.org',
        // Reputation & Blacklist Services
        'mxtoolbox.com',
        'www.mxtoolbox.com',
        'multirbl.valli.org',
        'www.multirbl.valli.org',
        'blacklistalert.org',
        'www.blacklistalert.org',
        'whatismyipaddress.com',
        'www.whatismyipaddress.com',
        'abuseipdb.com',
        'www.abuseipdb.com',
        // Web Security Scanners
        'detectify.com',
        'www.detectify.com',
        'probely.com',
        'www.probely.com',
        'pentest-tools.com',
        'www.pentest-tools.com',
        'webscantest.com',
        'www.webscantest.com',
        // Certificate Transparency & Monitoring
        'crt.sh',
        'www.crt.sh',
        'certificate.transparency.dev',
        'transparencyreport.google.com',
        // DNS & Network Analysis
        'dnslytics.com',
        'www.dnslytics.com',
        'dnsdumpster.com',
        'www.dnsdumpster.com',
        'viewdns.info',
        'www.viewdns.info',
        'robtex.com',
        'www.robtex.com',
        'centralops.net',
        'www.centralops.net',
        // Antivirus & Security Vendors
        'trendmicro.com',
        'www.trendmicro.com',
        'symantec.com',
        'www.symantec.com',
        'broadcom.com',
        'www.broadcom.com',
        'mcafee.com',
        'www.mcafee.com',
        'norton.com',
        'www.norton.com',
        'bitdefender.com',
        'www.bitdefender.com',
        'eset.com',
        'www.eset.com',
        'malwarebytes.com',
        'www.malwarebytes.com',
        'webroot.com',
        'www.webroot.com',
        'sophos.com',
        'www.sophos.com',
        'checkpoint.com',
        'www.checkpoint.com',
        // Additional Research & Analysis
        'urlquery.net',
        'www.urlquery.net',
        'wepawet.iseclab.org',
        'anubis.iseclab.org',
        'malwareurl.com',
        'www.malwareurl.com',
        'vxvault.net',
        'www.vxvault.net',
        'clean-mx.de',
        'www.clean-mx.de',
        'emergingthreats.net',
        'www.emergingthreats.net',
        'proofpoint.com',
        'www.proofpoint.com',
        'mimecast.com',
        'www.mimecast.com',
        'barracuda.com',
        'www.barracuda.com',
        // Threat Hunting & Intelligence
        'intezer.com',
        'www.intezer.com',
        'analyze.intezer.com',
        'app.intezer.com',
        'reversing.labs',
        'www.reversing.labs',
        'titanium-platform.com',
        'www.titanium-platform.com',
        'polyswarm.network',
        'www.polyswarm.network',
        // Additional Scanning Services
        'securi.net',
        'sitecheck.sucuri.net',
        'safebrowsing.google.com',
        'smartscreen.microsoft.com',
        'reputation.alienvault.com',
        'labs.alienvault.com',
    ];

    // Parse the referrer's host
    $referrerHost = parse_url($referrer, PHP_URL_HOST);

    if (!$referrerHost) {
        Log::debug('Request blocked due to invalid referrer format.', ['referrer' => $referrer]);
        return false; // Block requests with invalid referrer format
    }

    // Normalize the referrer host (remove www. prefix for comparison)
    $normalizedReferrerHost = preg_replace('/^www\./', '', strtolower($referrerHost));
    
    // Check if the referrer host is in the blocked domains list
    // Check both original and normalized versions
    if (in_array(strtolower($referrerHost), array_map('strtolower', $blockedDomains)) || 
        in_array($normalizedReferrerHost, array_map('strtolower', $blockedDomains))) {
        Log::debug('Request blocked due to referrer being in the blocked list.', ['referrerHost' => $referrerHost]);
        return false; // Block requests from blocked domains
    }

    // Check for subdomain variations of blocked domains
    foreach ($blockedDomains as $blockedDomain) {
        $normalizedBlockedDomain = preg_replace('/^www\./', '', strtolower($blockedDomain));
        if (str_ends_with($normalizedReferrerHost, '.' . $normalizedBlockedDomain) || 
            $normalizedReferrerHost === $normalizedBlockedDomain) {
            Log::debug('Request blocked due to referrer subdomain match.', [
                'referrerHost' => $referrerHost,
                'blockedDomain' => $blockedDomain
            ]);
            return false;
        }
    }

    // Check if the referrer contains the current domain
    $isValid = strpos($referrerHost, $currentDomain) !== false;
    // Log::debug('Referrer validation result: ' . ($isValid ? 'Valid' : 'Invalid'), [
    //     'referrerHost' => $referrerHost,
    //     'currentDomain' => $currentDomain
    // ]);

    return $isValid;
}


}