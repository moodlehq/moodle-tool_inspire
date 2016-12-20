import sys
import os
import time
import logging
import json

import numpy as np

from sklearn.cross_validation import train_test_split
from sklearn.linear_model import LogisticRegression

from sklearn.metrics import roc_curve, auc
from sklearn.preprocessing import MultiLabelBinarizer
from scipy import interp

# Local packages.
import logistic_utils
import roc_utils
from roc_curve import RocCurve

# Provided file dir.
filepath = sys.argv[1]
dirname = os.path.dirname(os.path.realpath(filepath))

# Percent to consider this test a success.
validation = float(sys.argv[2])

# Simple run identifier (I want them ordered).
runid = str(int(time.time()))

# Logging.
logfile = os.path.join(dirname, runid + '.log')
logging.basicConfig(filename=logfile,level=logging.DEBUG)

# Examples loading.
[X, y] = logistic_utils.get_examples(filepath)

solver = 'lbfgs'
multi_class = 'multinomial'
C = logistic_utils.get_c(X, y, solver, multi_class)

# Sanity check.
[classes, counts] = np.unique(y[:,-1:][:,0], return_counts=True)
logging.info('Number of examples by y value: %s' % str(counts))
info = logistic_utils.check_classes_balance(classes, counts)
if info != False:
    logging.warning(info)

curve_plot = RocCurve(dirname)

# Split examples into training set and test set (80% - 20%)
X_train, X_test, y_train, y_test = train_test_split(X, y, test_size=0.2)

# Init the classifier.
clf = LogisticRegression(solver=solver, tol=1e-1, C=C)

# Fit the training set. y should be an array-like.
clf.fit(X_train, y_train[:,0])

# Calculate scores.
y_score = clf.decision_function(X_test)
y_pred = clf.predict(X_test)

# We convert classes labels to a booleans matrix (1 column per class).
y_test = MultiLabelBinarizer().fit_transform(y_test)
y_pred = MultiLabelBinarizer().fit_transform(np.array([y_pred]).T)

# Store true positives, false positives as we will need them later to calculate the macro average.
tpr = dict()
fpr = dict()

# Iterate through the classes
for i, class_value in enumerate(classes):

    # Feed the roc curve.
    fpr[i], tpr[i], _ = roc_curve(y_test[:, i], y_score[:, i])

    # Calculate the area under this class values and set it as label.
    class_auc = auc(fpr[i], tpr[i])

    # y_test and y_pred are already binarized.
    class_test = y_test[:,i] == 1
    class_pred = y_pred[:,i] == 1

    # Calculate accuracy, sensitivity and specificity.
    [accuracy, precision, recall, phi] = roc_utils.calculate_metrics(class_test, class_pred)

    logging.info("Class %s accuracy: %.2f%%" % (class_value, (accuracy * 100)))
    logging.info("Class %s precision (predicted elements that are real): %.2f%%" % (class_value, (precision * 100)))
    logging.info("Class %s recall (real elements that are predicted): %.2f%%" % (class_value, (recall * 100)))
    logging.info("Class %s phi coefficient: %.2f%%" (phi * 100))

    # And add the data to the graph.
    label = 'Class {0} (area = {1:0.2f})'.format(class_value, class_auc)
    curve_plot.add(fpr[i], tpr[i], label)

# Feed the micro-average.
fpr_micro, tpr_micro, _ = roc_curve(y_test.ravel(), y_score.ravel())
auc_micro = auc(fpr_micro, tpr_micro)
label_micro = 'System average (micro-average) (area = {0:0.2f})'.format(auc_micro)
curve_plot.add(fpr_micro, tpr_micro, label_micro)

# Calculate the macro-average.
fpr_macro = np.unique(np.concatenate([fpr[i] for i,value in enumerate(classes)]))

# Then interpolate all ROC curves at this points.
mean_tpr = np.zeros_like(fpr_macro)
for i,value in enumerate(classes):
    mean_tpr += interp(fpr_macro, fpr[i], tpr[i])

# Finally average it and compute AUC.
mean_tpr /= len(classes)

tpr_macro = mean_tpr
auc_macro = auc(fpr_macro, tpr_macro)
label_macro = 'Classes average (macro-average) (area = {0:0.2f})'.format(auc_macro)
curve_plot.add(fpr_macro, tpr_macro, label_macro)

# Store the file.
fig_filepath = curve_plot.store(runid)
logging.info("Figure stored in " + fig_filepath)

result = dict()
result['id'] = int(runid)
result['auc'] = auc_macro
result['validation'] = validation

# TODO This should check classes accuracy, precision and recall as well.
if validation > auc_macro:
    exitcode = 1
else:
    # If we consider the classification as valid we store coeficients and intercepts.
    exitcode = 0

    np.savetxt(os.path.join(dirname, runid + '.coef.txt'), clf.coef_)
    np.savetxt(os.path.join(dirname, runid + '.intercept.txt'), clf.intercept_)

result['exitcode'] = exitcode
print(result)

sys.exit(exitcode)
