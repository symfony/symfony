local key = KEYS[1]
local weightKey = key .. ":weight"
local timeKey = key .. ":time"
local identifier = ARGV[1]
local now = tonumber(ARGV[2])
local ttlInSecond = tonumber(ARGV[3])
local limit = tonumber(ARGV[4])
local weight = tonumber(ARGV[5])

-- Remove expired values
redis.call("ZREMRANGEBYSCORE", timeKey, "-inf", now)
redis.call("ZINTERSTORE", weightKey, 2, weightKey, timeKey, "WEIGHTS", 1, 0)

-- Semaphore already acquired?
if redis.call("ZSCORE", timeKey, identifier) then
    return true
end

-- Try to get a semaphore
local semaphores = redis.call("ZRANGE", weightKey, 0, -1, "WITHSCORES")
local count = 0

for i = 1, #semaphores, 2 do
    count = count + semaphores[i+1]
end

-- Could we get the semaphore ?
if count + weight > limit then
    return false
end

-- Acquire the semaphore
redis.call("ZADD", timeKey, now + ttlInSecond, identifier)
redis.call("ZADD", weightKey, weight, identifier)

-- Extend the TTL
local maxExpiration = redis.call("ZREVRANGE", timeKey, 0, 0, "WITHSCORES")[2]
redis.call("EXPIREAT", weightKey, maxExpiration + 10)
redis.call("EXPIREAT", timeKey, maxExpiration + 10)

return true
