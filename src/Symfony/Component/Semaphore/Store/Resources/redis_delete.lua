local key = KEYS[1]
local weightKey = key .. ":weight"
local timeKey = key .. ":time"
local identifier = ARGV[1]

redis.call("ZREM", timeKey, identifier)
return redis.call("ZREM", weightKey, identifier)
