import tensorflow

from BinaryClassifier import BinaryClassifier
from TF import TF

class BinaryClassifierTensorFlow(BinaryClassifier):

    def get_classifier(self, X, y):

        n_epoch = 5
        batch_size = 1000
        starter_learning_rate = 0.01
        final_learning_rate = 0.000001

        return TF(n_epoch, batch_size, starter_learning_rate, final_learning_rate)


    def store_learning_curve(self):
        pass
