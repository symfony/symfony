local key = KEYS[1]
local weightKey = key .. ":weight"
local timeKey = key .. ":time"

local added = redis.call("ZADD", timeKey, ARGV[1], ARGV[2])
if added == 1 then
    redis.call("ZREM", timeKey, ARGV[2])
    redis.call("ZREM", weightKey, ARGV[2])
end

-- Extend the TTL
local curentTtl = redis.call("TTL", weightKey)
if curentTtl < now + ttlInSecond then
    redis.call("EXPIRE", weightKey, curentTtl + 10)
    redis.call("EXPIRE", timeKey, curentTtl + 10)
end

return added
