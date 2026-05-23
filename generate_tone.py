import wave
import math
import struct

sample_rate = 44100.0
frequency = 425.0
duration_on = 1.0
duration_off = 2.0

obj = wave.open('ringback.wav','w')
obj.setnchannels(1)
obj.setsampwidth(2)
obj.setframerate(sample_rate)

for i in range(int(sample_rate * duration_on)):
    value = int(10000 * math.sin(frequency * 2 * math.pi * (i / sample_rate)))
    data = struct.pack('<h', value)
    obj.writeframesraw(data)

for i in range(int(sample_rate * duration_off)):
    data = struct.pack('<h', 0)
    obj.writeframesraw(data)

obj.close()
