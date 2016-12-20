import numpy as np
from sklearn.cross_validation import train_test_split

from BinaryClassifier import BinaryClassifier
from NN import NN


class BinaryClassifierNNCV(BinaryClassifier):

    log_into_file = False

    def __init__(self, epsilon_values, reg_lambda, nn_hidden):
        self.epsilon_values = epsilon_values
        self.reg_lambda = reg_lambda
        self.nn_hidden = nn_hidden
        self.epsilon_rates = {}

        super(BinaryClassifierNNCV, self).__init__()

    def fit(self, X, y):
        """X and y should not include the test dataset.
        """

        for e in self.epsilon_values:

            # Using 10000 iterations to evaluate the classifier performance.
            # We train it 3 times to make an average.
            for i in range(3):

                X_train, X_cv, y_train, y_cv = train_test_split(X, y, test_size=0.3)

                nn = NN(10000, e, self.reg_lambda, self.nn_hidden)
                nn.fit(X_train, y_train[:,0])
                self.rate_prediction(nn, X_cv, y_cv)

            avg_phi = np.mean(self.phis)
            self.epsilon_rates[e] = avg_phi
            print("Epsilon value %f phi: %f" % (e, avg_phi))

            # We don't want previous epsilon's values runs to interfere.
            self.reset_rates()


    def get_best_epsilon(self):
        best_epsilon = max(self.epsilon_rates, key=self.epsilon_rates.get)
        print('Best epsilon value: %f (phi = %f)' % (best_epsilon, self.epsilon_rates[best_epsilon]))
        return best_epsilon

