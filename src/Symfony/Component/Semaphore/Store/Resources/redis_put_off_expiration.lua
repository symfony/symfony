local key = KEYS[1]
local weightKey = key .. ":weight"
local timeKey = key .. ":time"

local added = redis.call("ZADD", timeKey, ARGV[1], ARGV[2])
if added == 1 then
    redis.call("ZREM", timeKey, ARGV[2])
    redis.call("ZREM", weightKey, ARGV[2])
end

-- Extend the TTL
local maxExpiration = redis.call("ZREVRANGE", timeKey, 0, 0, "WITHSCORES")[2]
if nil == maxExpiration then
    return 1
end

redis.call("EXPIREAT", weightKey, maxExpiration + 10)
redis.call("EXPIREAT", timeKey, maxExpiration + 10)

return added
