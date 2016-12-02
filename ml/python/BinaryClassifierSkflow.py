import tensorflow.contrib.learn as skflow

from BinaryClassifier import BinaryClassifier

class BinaryClassifierSkflow(BinaryClassifier):

    def store_model(self):
        # TODO Coefs and biases are stored differently in skflow.
        pass

    def store_learning_curve(self):
        pass

    def get_classifier(self, X, y):
        return skflow.TensorFlowLinearClassifier(n_classes=2)
