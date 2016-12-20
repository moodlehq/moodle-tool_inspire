import sys
import os
import time
import logging
import json
import math

import numpy as np
from sklearn.utils import shuffle

from sklearn.cross_validation import train_test_split
from sklearn.linear_model import LogisticRegression
from sklearn.metrics import roc_curve, auc
from sklearn import preprocessing

import tensorflow as tf

import logistic_utils
from RocCurve import RocCurve

np.set_printoptions(suppress=True)
np.set_printoptions(precision=5)
np.set_printoptions(threshold=np.inf)

# Simple run identifier (I want them ordered).
runid = str(int(time.time()))

# Missing arguments.
if len(sys.argv) < 4:
    result = dict()
    result['id'] = int(runid)
    result['exitcode'] = 1
    result['errors'] = ['Missing arguments, you should set the minimum accuracy. Received: ' + ' '.join(sys.argv)]
    print(json.dumps(result))
    sys.exit(result['exitcode'])

# Provided file dir.
filepath = sys.argv[1]
dirname = os.path.dirname(os.path.realpath(filepath))

# Percent to consider this test a success. Defaults to 0.7, which is the strong association boundary.
accepted_phi = float(sys.argv[2])
accepted_deviation = float(sys.argv[3])

# Logging.
logfile = os.path.join(dirname, runid + '.log')
logging.basicConfig(filename=logfile,level=logging.DEBUG)

# Examples loading.
[X, Y] = logistic_utils.get_examples(filepath)

accuracies = []
precisions = []
recalls = []
phis = []
aucs = []

_, n_features = X.shape

# Preprocess given data.
# Scale all examples features around the feature mean value.
X = logistic_utils.scale(X)

# Split examples into training set and test set (80% - 20%)
X_train, X_test, y_train, y_test_single_label = train_test_split(X, Y, test_size=0.2)

# Convert single column 1/0 to 2 columns.
y_train = preprocessing.MultiLabelBinarizer().fit_transform(y_train)
y_test = preprocessing.MultiLabelBinarizer().fit_transform(y_test_single_label)

# Sanity check.
n_classes = 2
classes = [1, 0]
counts = []
y_array = np.array(y_train.T[0])
counts.append(np.count_nonzero(y_array))
counts.append(len(y_array) - np.count_nonzero(y_array))
logging.info('Number of examples by y value: %s' % str(counts))
balanced_classes = logistic_utils.check_classes_balance(counts)
if balanced_classes != False:
    logging.warning(balanced_classes)

# ROC curve.
roc_curve_plot = RocCurve(dirname, 2)

# tf stuff.
x = tf.placeholder(tf.float32, [None, n_features], name='x')
y_ = tf.placeholder(tf.float32, [None, n_classes], name='dataset-y')

W = tf.Variable(tf.zeros([n_features, n_classes]), name='weights')
b = tf.Variable(tf.zeros([n_classes]), name='bias')

# Predicted y.
model = tf.matmul(x, W) + b

y = tf.nn.softmax(model)

cross_entropy = -tf.reduce_sum(y_ * tf.log(tf.clip_by_value(y,1e-10,1.0)))
loss = tf.reduce_mean(cross_entropy)
ce_summ = tf.scalar_summary("loss", loss)

# Training.
n_epoch = 5
batch_size = 50
n_train_examples, n_train_features = X_train.shape
iterations = int(n_train_examples / batch_size)
total_iterations = n_epoch * iterations
starter_learning_rate = 0.4
final_learning_rate = 0.01

# Calculate decay_rate.
decay_rate = math.pow(final_learning_rate / starter_learning_rate, (1. / float(total_iterations)))

# Learning rate decreasing over time.
global_step = tf.Variable(0, trainable=False)
learning_rate = tf.train.exponential_decay(starter_learning_rate, global_step,
                                           total_iterations, decay_rate, staircase=True)

train_step = tf.train.GradientDescentOptimizer(learning_rate).minimize(loss)

sess = tf.Session()

# Summaries.
merged_summaries = tf.merge_all_summaries()
train_writer = tf.train.SummaryWriter('/home/davidm/Desktop/tensor', sess.graph)
test_writer = tf.train.SummaryWriter('/home/davidm/Desktop/tensor')

init = tf.initialize_all_variables()

sess.run(init)

# Train in batchs.
for e in range(n_epoch):
    for i in range(iterations):

        offset = i * batch_size
        it_end = offset + batch_size
        if it_end > n_train_examples:
            it_end = n_train_examples - 1

        batch_xs = X_train[offset:it_end]
        batch_ys = y_train[offset:it_end]
        feed = {x: batch_xs, y_: batch_ys}
        sess.run(train_step, feed_dict=feed)

y_pred = sess.run(tf.argmax(y, 1), feed_dict={x: X_test})

# Get only the max value column scores.
y_scores = sess.run(model, feed_dict={x: X_test})
y_score = []
for i, scores in enumerate(y_scores):
    value = scores[y_pred[i]]
    if (y_pred[i] == 0):
        value = value * -1
    y_score.append(value)
y_score = np.array(y_score)

y_test_array = y_test_single_label.flatten()
[acc, prec, rec, ph] = logistic_utils.calculate_metrics(y_test_array == 1, y_pred == 1)

fpr, tpr, _ = roc_curve(y_test_array, y_score)
auc_value = auc(fpr, tpr)

accuracies.append(acc)
precisions.append(prec)
recalls.append(rec)
phis.append(ph)
aucs.append(auc_value)

# Draw it.
roc_curve_plot.add(fpr, tpr, 'Positives')

# Store the figure.
fig_filepath = roc_curve_plot.store(runid)
logging.info("Figure stored in " + fig_filepath)

# Return results.
result = logistic_utils.get_bin_results(accuracies, precisions, recalls, phis, aucs, accepted_phi, accepted_deviation)

# Add the run id to identify it in the caller.
result['id'] = int(runid)

logging.info("Accuracy: %.2f%%" % (result['accuracy'] * 100))
logging.info("Precision (predicted elements that are real): %.2f%%" % (result['precision'] * 100))
logging.info("Recall (real elements that are predicted): %.2f%%" % (result['recall'] * 100))
logging.info("Phi coefficient: %.2f%%" % (result['phi'] * 100))
logging.info("AUC standard desviation: %.4f" % (result['auc_deviation']))

print(json.dumps(result))
sys.exit(result['exitcode'])
