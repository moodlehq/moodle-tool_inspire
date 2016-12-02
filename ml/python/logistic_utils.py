import os
import math
import numpy as np
import matplotlib.pyplot as plt

from sklearn.learning_curve import learning_curve
from sklearn.linear_model import LogisticRegressionCV
from sklearn.utils import shuffle
from sklearn import preprocessing

def get_examples(filepath):

    examples = np.loadtxt(filepath, delimiter=',', dtype='float')
    examples = shuffle(examples)

    # All columns but the last one.
    X = np.array(examples[:,0:-1])

    # Only the last one.
    y = np.array(examples[:,-1:])

    return [X, y]

def check_classes_balance(counts):
    for item1 in counts:
        for item2 in counts:
            if item1 > (item2 * 3):
                return 'Provided classes are very unbalanced, predictions may not be accurate.'
    return False

def limit_value(value, lower_bounds, upper_bounds):
    # Limits the value by lower and upper boundaries.
    if value < (lower_bounds - 1):
        return lower_bounds
    elif value > (upper_bounds + 1):
        return upper_bounds
    else:
        return value

def scale(X):

    # Limit values to 2 standard deviations from the mean in order
    # to avoid extreme values.
    devs = np.std(X, axis=0) * 2
    means = np.mean(X, axis=0)
    lower_bounds = means - devs
    upper_bounds = means + devs

    # Switch to an array by features to loop through bounds.
    Xf = np.rollaxis(X, axis=1)
    for i, values in enumerate(Xf):
        Xf[i] = [limit_value(x, lower_bounds[i], upper_bounds[i]) for x in Xf[i]]

    # Return to an array by examples.
    X = np.rollaxis(Xf, axis=1)

    # Reduce values.
    return preprocessing.robust_scale(X, axis=0, copy=False)

def get_c(X, y, solver, multi_class):

    # Cross validation - to select the best constants.
    lgcv = LogisticRegressionCV(solver=solver, multi_class=multi_class);
    lgcv.fit(X, y[:,0])

    if multi_class == 'multinomial':
        # All C values are the same.
        C = lgcv.C_[0]

    elif len(lgcv.C_) == 1:
        C = lgcv.C_[0]

    else:
        # Chose the best C = the class with more examples.
        # Ideally multiclass problems will be multinomial.
        [values, counts] = np.unique(y[:,0], return_counts=True)
        C = lgcv.C_[np.argmax(counts)]
        print('From all classes best C values (%s), %f has been selected' % (str(lgcv.C_), C))

    return C

def save_elbow(runid, dirname, figid, distances):

    plt.figure(figid)
    plt.ylabel("Elbow")
    plt.xlabel("k")
    plt.title("Examples distance to centroids")

    plt.plot(distances)

    filepath = os.path.join(dirname, runid + '.elbow.png')
    plt.savefig(filepath, format='png')

    return filepath


def save_silhouette(runid, dirname, figid, silhouettes):

    plt.figure(figid)
    plt.ylabel("Silhouette")
    plt.xlabel("k")
    plt.title("Silouette for K-means cell's behaviour")

    plt.plot(silhouettes)

    filepath = os.path.join(dirname, runid + '.silhouette.png')
    plt.savefig(filepath, format='png')

    return filepath


def save_learning_curve(runid, dirname, figid, X, y, clf):

    plt.figure(figid)
    plt.xlabel("Training examples")
    plt.ylabel("Error")

    train_sizes, train_scores, test_scores = learning_curve(clf, X, y[:,0])

    train_error_mean = 1 - np.mean(train_scores, axis=1)
    train_scores_std = np.std(train_scores, axis=1)
    test_error_mean = 1 - np.mean(test_scores, axis=1)
    test_scores_std = np.std(test_scores, axis=1)
    plt.grid()

    plt.fill_between(train_sizes, train_error_mean + train_scores_std,
                     train_error_mean - train_scores_std, alpha=0.1,
                     color="r")
    plt.fill_between(train_sizes, test_error_mean + test_scores_std,
                     test_error_mean - test_scores_std, alpha=0.1, color="g")
    plt.plot(train_sizes, train_error_mean, 'o-', color="r",
             label="Training error")
    plt.plot(train_sizes, test_error_mean, 'o-', color="g",
             label="Cross-validation error")
    plt.legend(loc="best")

    filepath = os.path.join(dirname, runid + '.learning-curve.png')
    plt.savefig(filepath, format='png')

    return filepath

def calculate_metrics(y_test_true, y_pred_true):

    test_p = y_test_true
    test_n = np.invert(test_p)

    pred_p = y_pred_true
    pred_n = np.invert(pred_p)

    pp = np.count_nonzero(test_p)
    nn = np.count_nonzero(test_n)
    tp = np.count_nonzero(test_p * pred_p)
    tn = np.count_nonzero(test_n * pred_n)
    fn = np.count_nonzero(test_p * pred_n)
    fp = np.count_nonzero(test_n * pred_p)

    accuracy = (tp + tn) / float(pp + nn)
    if tp != 0 or fp != 0:
        precision = tp / float(tp + fp)
    else:
        precision = 0
    if tp != 0 or fn != 0:
        recall = tp / float(tp + fn)
    else:
        recall = 0

    denominator = (tp + fp) * (tp + fn) * (tn + fp) * (tn + fn)
    if denominator != 0:
        phi = ( ( tp * tn) - (fp * fn) ) / math.sqrt(denominator)
    else:
        phi = 0

    return [accuracy, precision, recall, phi]

def get_bin_results(accuracies, precisions, recalls, phis,
                    aucs, accepted_phi, accepted_deviation):

    avg_accuracy = np.mean(accuracies)
    avg_precision = np.mean(precisions)
    avg_recall = np.mean(recalls)
    avg_phi = np.mean(phis)
    avg_aucs = np.mean(aucs)

    result = dict()
    result['auc'] = avg_aucs
    result['accuracy'] = avg_accuracy
    result['precision'] = avg_precision
    result['recall'] = avg_recall
    result['phi'] = avg_phi
    result['auc_deviation'] = np.std(aucs)
    result['accepted_phi'] = accepted_phi
    result['accepted_deviation'] = accepted_deviation

    result['exitcode'] = 0
    result['errors'] = []

    # If deviation is too high we may need more records to report if
    # this model is reliable or not.
    auc_deviation = np.std(aucs)
    if auc_deviation > accepted_deviation:
        result['errors'].append('The results obtained varied too much,'
            + ' we need more examples to check if this model is valid.'
            + ' Model deviation = %f, accepted deviation = %f' \
            % (auc_deviation, accepted_deviation))
        result['exitcode'] = 1

    if avg_phi < accepted_phi:
        result['errors'].append('The model is not good enough. Model phi ='
            + ' %f, accepted phi = %f' \
            % (avg_phi, accepted_phi))
        result['exitcode'] = 1

    return result
