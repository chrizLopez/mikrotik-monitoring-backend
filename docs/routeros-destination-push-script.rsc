# RouterOS destination push sketch
#
# This extends the existing push payload with a destinations array.
# It is intentionally conservative: RouterOS cannot reliably identify every app
# from counters alone, so this should be treated as DNS/connection-based ranking.
#
# Required router settings:
# - DNS cache enabled: /ip dns set cache-size=4096KiB
# - Connection tracking enabled
# - Existing queue/interface/health push script remains installed
#
# Practical implementation notes:
# - Build destinations from active connections matched against /ip dns cache
#   A records. Sum orig/repl bytes when available.
# - Categorize well-known domains:
#   games: roblox, steam, epicgames, riotgames, garena, minecraft
#   apps: youtube, facebook, tiktok, netflix, spotify, instagram, messenger
#   sites: everything else with a resolved host name
# - Limit to the top 10-20 rows per push to keep payload size small.
#
# Payload shape to append:
# "destinations":[
#   {"category":"apps","name":"youtube.com","visits":12,"total_bytes":987654321},
#   {"category":"games","name":"roblox.com","visits":4,"total_bytes":123456789},
#   {"category":"sites","name":"example.com","visits":2,"total_bytes":456789}
# ]
#
# If DNS-over-HTTPS is used by clients, RouterOS may only see the DoH provider
# instead of the real site. For better accuracy, force LAN clients to use router
# DNS or add a dedicated DNS logger/exporter later.
