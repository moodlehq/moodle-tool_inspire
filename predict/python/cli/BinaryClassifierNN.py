from BinaryClassifier import BinaryClassifier
from NN import NN
from BinaryClassifierNNCV import BinaryClassifierNNCV

class BinaryClassifierNN(BinaryClassifier):

    def get_classifier(self, X, y):

        epsilon = None
        nn_iterations=50
        reg_lambda = 0.0005

        # Number of elements per hidden layer dependant on the number of features.
        n_samples, n_features = X.shape
        if n_features == 1:
            nn_hidden = []
        elif n_features == 2:
            nn_hidden = []
        elif n_features > 5:
            nn_hidden = [3]
        elif n_features > 10:
            nn_hidden = [6, 3]
        elif n_features > 50:
            nn_hidden = [20, 5]
        nn_hidden = [5, 3]

        # Find out the best epsilon value.
        if hasattr(self, 'epsilon') == False:
            epsilon_values = [0.000001, 0.000005, 0.00001, 0.00005, 0.0001, 0.0005, 0.001,
                 0.005, 0.01, 0.05, 0.1, 0.5, 1, 5, 10]
            cv = BinaryClassifierNNCV(epsilon_values, reg_lambda, nn_hidden)
            cv.fit(X, y)
            self.epsilon = cv.get_best_epsilon()

        # Return the classifier using the epsilon value we selected.
        return NN(nn_iterations, self.epsilon, reg_lambda, nn_hidden)

    def store_learning_curve(self):
        pass
