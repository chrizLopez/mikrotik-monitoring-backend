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
      :local displayName $host;

      :if (([:typeof [:find $host "youtube"]] != "nil") || ([:typeof [:find $host "googlevideo"]] != "nil") || ([:typeof [:find $host "ytimg"]] != "nil")) do={ :set category "apps"; :set displayName "YouTube"; }
      :if (([:typeof [:find $host "tiktok"]] != "nil") || ([:typeof [:find $host "tiktokcdn"]] != "nil") || ([:typeof [:find $host "pangle"]] != "nil") || ([:typeof [:find $host "byteoversea"]] != "nil")) do={ :set category "apps"; :set displayName "TikTok"; }
      :if (([:typeof [:find $host "facebook"]] != "nil") || ([:typeof [:find $host "fbcdn"]] != "nil") || ([:typeof [:find $host "fbsbx"]] != "nil") || ([:typeof [:find $host "messenger"]] != "nil")) do={ :set category "apps"; :set displayName "Facebook"; }
      :if (([:typeof [:find $host "instagram"]] != "nil") || ([:typeof [:find $host "cdninstagram"]] != "nil")) do={ :set category "apps"; :set displayName "Instagram"; }
      :if (([:typeof [:find $host "netflix"]] != "nil") || ([:typeof [:find $host "nflxvideo"]] != "nil")) do={ :set category "apps"; :set displayName "Netflix"; }
      :if (([:typeof [:find $host "spotify"]] != "nil") || ([:typeof [:find $host "scdn.co"]] != "nil")) do={ :set category "apps"; :set displayName "Spotify"; }

      :if (([:typeof [:find $host "callofduty"]] != "nil") || ([:typeof [:find $host "codm"]] != "nil") || ([:typeof [:find $host "activision"]] != "nil") || ([:typeof [:find $host "demonware"]] != "nil") || ([:typeof [:find $host "garena"]] != "nil") || ([:typeof [:find $host "gfaren"]] != "nil")) do={ :set category "games"; :set displayName "Call of Duty"; }
      :if ([:typeof [:find $host "roblox"]] != "nil") do={ :set category "games"; :set displayName "Roblox"; }
      :if (([:typeof [:find $host "steam"]] != "nil") || ([:typeof [:find $host "steamcontent"]] != "nil") || ([:typeof [:find $host "steampowered"]] != "nil")) do={ :set category "games"; :set displayName "Steam"; }
      :if ([:typeof [:find $host "epicgames"]] != "nil") do={ :set category "games"; :set displayName "Epic Games"; }
      :if (([:typeof [:find $host "riotgames"]] != "nil") || ([:typeof [:find $host "valorant"]] != "nil")) do={ :set category "games"; :set displayName "Riot Games"; }
      :if (([:typeof [:find $host "mobilelegends"]] != "nil") || ([:typeof [:find $host "moonton"]] != "nil")) do={ :set category "games"; :set displayName "Mobile Legends"; }
      :if ([:typeof [:find $host "minecraft"]] != "nil") do={ :set category "games"; :set displayName "Minecraft"; }

      :set payload ($payload . "&destinations[" . $count . "][category]=" . $category . "&destinations[" . $count . "][name]=" . $displayName . "&destinations[" . $count . "][visits]=1&destinations[" . $count . "][total_bytes]=0");
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
