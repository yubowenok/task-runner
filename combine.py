import sys
import random

dir = sys.argv[1]

keyDict = {}
keys = []
pairs = []
for line in sys.stdin:
  tokens = line.strip().split('\t', 1)
  if len(tokens) < 1:
	  continue
  key = tokens[0]
  if key not in keyDict:
    keyDict[key] = True
    keys.append(key)
  
  value = ''
  if len(tokens) == 2:
    value = tokens[1]
  else:
    raise Exception('unexpected number of tokens')
  pairs.append([key, value])

p1 = open(dir + 'red_input1', 'w')
p2 = open(dir + 'red_input2', 'w')
h1 = '012345678'

random.shuffle(keys)
cutoff = len(keys) / 2
for i in xrange(len(keys)):
  if i < cutoff:
    keyDict[keys[i]] = 1;
  else:
    keyDict[keys[i]] = 2;

for pair in pairs:
  key, value = pair[0], pair[1]
  
  if keyDict[key] == 1:
    p1.write('%s\t%s\n' % (key, value))
  else:
    p2.write('%s\t%s\n' % (key, value))

p1.close()
p2.close()

