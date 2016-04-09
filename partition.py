import sys
import os
import random

dir = sys.argv[1]
filepath = sys.argv[2]
basename = os.path.basename(filepath)

lines = []
for line in sys.stdin:
  lines.append(line.strip())

random.shuffle(lines)

p1 = open(dir + 'part00000-' + basename, 'w')
p2 = open(dir + 'part00001-' + basename, 'w')

cutoff = len(lines) / 2
for index, line in enumerate(lines):
  if index < cutoff:
    p1.write('%s\n' % line)
  else:
    p2.write('%s\n' % line)

p1.close()
p2.close()

