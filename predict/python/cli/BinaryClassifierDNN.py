import tensorflow.contrib.learn as skflow

from BinaryClassifierSkflow import BinaryClassifierSkflow

class BinaryClassifierDNN(BinaryClassifierSkflow):

    def get_classifier(self, X, y):
        return skflow.TensorFlowDNNClassifier(hidden_units=[5, 3], n_classes=2)
