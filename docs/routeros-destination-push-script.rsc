# Push DNS-cache destination samples to Laravel.
#
# Replace __TOKEN__ with the same value used by MIKROTIK_PUSH_TOKEN.
# This runs as a companion to the main PushStatsToLaravel script.
# Accuracy depends on router DNS visibility. DNS-over-HTTPS/VPN traffic may not
# expose the real destination name.

:log info "dest-push: start";
:local token "__TOKEN__";
:local endpoint ("https://dashboard.phsolarsizer.com/api/mikrotik/push?token=" . $token);
:local routerName [/system identity get name];
:local payload ("router_name=" . $routerName);
:local count 0;

:foreach i in=[/ip dns cache find] do={
  :if ($count < 20) do={
    :local host [/ip dns cache get $i name];
    :if (([:len $host] > 0) && ([:typeof [:find $host ".lan"]] = "nil") && ([:typeof [:find $host "router."]] = "nil") && ([:typeof [:find $host "in-addr.arpa"]] = "nil")) do={
      :local category "sites";

      :if (([:typeof [:find $host "roblox"]] != "nil") || ([:typeof [:find $host "steam"]] != "nil") || ([:typeof [:find $host "epicgames"]] != "nil") || ([:typeof [:find $host "riotgames"]] != "nil") || ([:typeof [:find $host "garena"]] != "nil") || ([:typeof [:find $host "minecraft"]] != "nil")) do={ :set category "games"; }
      :if (([:typeof [:find $host "youtube"]] != "nil") || ([:typeof [:find $host "googlevideo"]] != "nil") || ([:typeof [:find $host "tiktok"]] != "nil") || ([:typeof [:find $host "pangle"]] != "nil") || ([:typeof [:find $host "facebook"]] != "nil") || ([:typeof [:find $host "fbcdn"]] != "nil") || ([:typeof [:find $host "instagram"]] != "nil") || ([:typeof [:find $host "netflix"]] != "nil") || ([:typeof [:find $host "spotify"]] != "nil")) do={ :set category "apps"; }

      :set payload ($payload . "&destinations[" . $count . "][category]=" . $category . "&destinations[" . $count . "][name]=" . $host . "&destinations[" . $count . "][visits]=1&destinations[" . $count . "][total_bytes]=0");
      :set count ($count + 1);
    }
  }
}

:if ($count = 0) do={
  :log warning "dest-push: no destinations found";
} else={
  /tool fetch url=$endpoint http-method=post http-header-field="Content-Type: application/x-www-form-urlencoded" http-data=$payload keep-result=no;
  :log info ("dest-push: sent destinations=" . $count);
}
