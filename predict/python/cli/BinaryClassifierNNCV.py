import math

import numpy as np
from sklearn.cross_validation import train_test_split

from NN import NN


class BinaryClassifierNNCV():

    log_into_file = False

    def __init__(self, epsilon_values, reg_lambda, nn_hidden):
        self.epsilon_values = epsilon_values
        self.reg_lambda = reg_lambda
        self.nn_hidden = nn_hidden
        self.epsilon_rates = {}

        self.reset_rates()

    def fit(self, X, y):
        """X and y should not include the test dataset.
        """

        for e in self.epsilon_values:

            # Using 100 iterations to evaluate the classifier performance.
            # We train it 3 times to make an average.
            for i in range(3):

                X_train, X_cv, y_train, y_cv = train_test_split(X, y, test_size=0.3)

                nn = NN(100, e, self.reg_lambda, self.nn_hidden)
                nn.fit(X_train, y_train[:,0])
                self.rate_prediction(nn, X_cv, y_cv)

            avg_phi = np.mean(self.phis)
            self.epsilon_rates[e] = avg_phi
            print("Epsilon value %f phi: %f" % (e, avg_phi))

            # We don't want previous epsilon's values runs to interfere.
            self.reset_rates()


    def rate_prediction(self, classifier, X_test, y_test):
        """Copied from BinaryClassifier and adapted"""

        # Calculate scores.
        y_pred = classifier.predict(X_test)

        # Transform it to an array.
        y_test = y_test.T[0]

        # Calculate accuracy, sensitivity and specificity.
        [acc, prec, rec, ph] = self.calculate_metrics(y_test == 1, y_pred == 1)
        self.accuracies.append(acc)
        self.precisions.append(prec)
        self.recalls.append(rec)
        self.phis.append(ph)


    def calculate_metrics(self, y_test_true, y_pred_true):
        """Copied from BinaryClassifier"""

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

    def reset_rates(self):
        self.accuracies = []
        self.precisions = []
        self.recalls = []
        self.phis = []

    def get_best_epsilon(self):
        best_epsilon = max(self.epsilon_rates, key=self.epsilon_rates.get)
        print('Best epsilon value: %f (phi = %f)' % (best_epsilon, self.epsilon_rates[best_epsilon]))
        return best_epsilon
